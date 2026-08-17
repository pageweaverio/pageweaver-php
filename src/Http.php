<?php

namespace PageWeaver;

/**
 * Internal HTTP client: attaches the API key, serializes JSON, applies a timeout, retries
 * transient failures with backoff, and maps non-2xx responses to a typed
 * {@link PageWeaverAPIException} subclass and transport failures to
 * {@link PageWeaverConnectionException}. Every resource is built on this. Not part of the public API.
 *
 * @internal
 */
class Http
{
    private const RETRIABLE_STATUS = [429, 500, 502, 503, 504];
    private const SAFE_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE'];
    private const DEFAULT_MAX_RETRIES = 2;
    private const DEFAULT_BASE_DELAY_MS = 300;
    private const DEFAULT_MAX_DELAY_MS = 5000;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $timeout;

    /** @var array{maxRetries:int,baseDelayMs:int,maxDelayMs:int} */
    private $retry;

    /**
     * @param array{maxRetries?:int,baseDelayMs?:int,maxDelayMs?:int} $retry Automatic retry policy
     *     for transient failures (429, 5xx, network errors) on requests safe to repeat:
     *     GET/HEAD/PUT/DELETE always, POST only when an `Idempotency-Key` header is present. Pass
     *     `['maxRetries' => 0]` to disable.
     */
    public function __construct(string $apiKey, string $baseUrl, int $timeout, array $retry = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->retry = [
            'maxRetries' => $retry['maxRetries'] ?? self::DEFAULT_MAX_RETRIES,
            'baseDelayMs' => $retry['baseDelayMs'] ?? self::DEFAULT_BASE_DELAY_MS,
            'maxDelayMs' => $retry['maxDelayMs'] ?? self::DEFAULT_MAX_DELAY_MS,
        ];
    }

    /**
     * Perform a request and decode a JSON response into an associative array. A 204/empty body
     * returns an empty array.
     *
     * @param array<string,mixed>|null           $body    JSON request body, or null for no body.
     * @param array<string,string|int|float|null> $query   Query params; null/empty values are dropped.
     * @param array<string,string>                $headers Extra headers.
     * @return array<string,mixed>
     */
    public function json(string $method, string $path, ?array $body = null, array $query = [], array $headers = [], bool $noAuth = false): array
    {
        $res = $this->send($method, $path, $body, $query, $headers, $noAuth);
        $text = $res['body'];
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Perform a multipart/form-data request (file upload) and decode a JSON response into an
     * associative array. Multipart bodies are never retried (the stream can't be safely replayed),
     * regardless of the client's retry policy.
     *
     * @param array<string,string|int|float|bool|null> $fields String fields; null/undefined values are dropped.
     * @param array<string,array{data:string,filename:string,contentType?:string|null}> $files Field name => file part.
     * @param array<string,string> $headers
     * @return array<string,mixed>
     */
    public function multipart(string $method, string $path, array $fields, array $files, array $headers = []): array
    {
        $res = $this->sendMultipart($method, $path, $fields, $files, $headers);
        $text = $res['body'];
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Perform a request and return the raw response body as bytes (for PDF downloads).
     *
     * @param array<string,string> $headers
     */
    public function bytes(string $method, string $path, array $headers = [], bool $noAuth = false): string
    {
        $res = $this->send($method, $path, null, [], $headers, $noAuth);
        return $res['body'];
    }

    /**
     * Perform a request and return the raw parts (status, headers, body). For content-negotiated
     * endpoints where the body may be JSON or bytes depending on the response (e.g. synchronous create).
     *
     * @param array<string,mixed>|null $body
     * @param array<string,string>     $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public function raw(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        return $this->send($method, $path, $body, [], $headers, false);
    }

    /**
     * Fetch an absolute URL (e.g. a signed download URL) with no auth header and return its bytes.
     */
    public function fetchUrlBytes(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new PageWeaverConnectionException('Request failed: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            throw new PageWeaverAPIException(
                $status,
                "Failed to download from {$url}: {$status}",
                null,
                null,
                (string) $raw
            );
        }
        return (string) $raw;
    }

    /**
     * @param array<string,mixed>|null            $body
     * @param array<string,string|int|float|null> $query
     * @param array<string,string>                $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function send(string $method, string $path, ?array $body, array $query, array $headers, bool $noAuth): array
    {
        $url = $this->baseUrl . $path . $this->buildQuery($query);
        $upperMethod = strtoupper($method);
        $lowerHeaders = array_change_key_case($headers, CASE_LOWER);
        $retryable = $this->retry['maxRetries'] > 0
            && (in_array($upperMethod, self::SAFE_METHODS, true)
                || ($upperMethod === 'POST' && isset($lowerHeaders['idempotency-key'])));

        $attempt = 0;
        for (;;) {
            $ch = curl_init($url);

            $requestHeaders = ['accept: application/json'];
            if (!$noAuth) {
                $requestHeaders[] = 'x-api-key: ' . $this->apiKey;
            }
            foreach ($headers as $name => $value) {
                $requestHeaders[] = $name . ': ' . $value;
            }

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_HEADER, true);

            if ($body !== null) {
                $requestHeaders[] = 'content-type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($body));
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

            $response = curl_exec($ch);
            if ($response === false) {
                $err = curl_error($ch);
                $errno = curl_errno($ch);
                curl_close($ch);
                if ($retryable && $attempt < $this->retry['maxRetries']) {
                    $this->sleepMs($this->backoffDelayMs($attempt));
                    $attempt++;
                    continue;
                }
                if ($errno === CURLE_OPERATION_TIMEDOUT) {
                    throw new PageWeaverConnectionException("Request timed out after {$this->timeout}s.");
                }
                throw new PageWeaverConnectionException('Request failed: ' . $err);
            }

            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            $response = (string) $response;
            $rawHeaders = substr($response, 0, $headerSize);
            $responseBody = substr($response, $headerSize);
            $responseHeaders = $this->parseHeaders($rawHeaders);

            if ($status < 200 || $status >= 300) {
                if ($retryable && in_array($status, self::RETRIABLE_STATUS, true) && $attempt < $this->retry['maxRetries']) {
                    $retryAfter = $this->parseRetryAfter($responseHeaders['retry-after'] ?? null);
                    $this->sleepMs($this->backoffDelayMs($attempt, $retryAfter));
                    $attempt++;
                    continue;
                }
                throw $this->toApiError($status, $responseBody, $responseHeaders);
            }

            return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
        }
    }

    /**
     * Multipart requests are never retried (the file stream can't be safely replayed).
     *
     * @param array<string,string|int|float|bool|null> $fields
     * @param array<string,array{data:string,filename:string,contentType?:string|null}> $files
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    private function sendMultipart(string $method, string $path, array $fields, array $files, array $headers): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        $requestHeaders = ['accept: application/json', 'x-api-key: ' . $this->apiKey];
        foreach ($headers as $name => $value) {
            $requestHeaders[] = $name . ': ' . $value;
        }

        $postFields = [];
        foreach ($fields as $key => $value) {
            if ($value === null) {
                continue;
            }
            $postFields[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        $tmpPaths = [];
        foreach ($files as $field => $file) {
            $tmpPath = tempnam(sys_get_temp_dir(), 'pw');
            if ($tmpPath === false) {
                throw new PageWeaverConnectionException('Unable to create a temporary file for upload.');
            }
            file_put_contents($tmpPath, $file['data']);
            $tmpPaths[] = $tmpPath;
            $postFields[$field] = new \CURLFile(
                $tmpPath,
                $file['contentType'] ?? 'application/octet-stream',
                $file['filename']
            );
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            foreach ($tmpPaths as $p) {
                @unlink($p);
            }
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new PageWeaverConnectionException("Request timed out after {$this->timeout}s.");
            }
            throw new PageWeaverConnectionException('Request failed: ' . $err);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        foreach ($tmpPaths as $p) {
            @unlink($p);
        }

        $response = (string) $response;
        $rawHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        $responseHeaders = $this->parseHeaders($rawHeaders);

        if ($status < 200 || $status >= 300) {
            throw $this->toApiError($status, $responseBody, $responseHeaders);
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
    }

    /**
     * @param array<string,string> $responseHeaders
     */
    private function toApiError(int $status, string $rawBody, array $responseHeaders = []): PageWeaverAPIException
    {
        $body = null;
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            $body = ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? $rawBody : $decoded;
        }

        $record = is_array($body) ? $body : [];
        $message = "Request failed with status {$status}";
        if (isset($record['message'])) {
            if (is_string($record['message'])) {
                $message = $record['message'];
            } elseif (is_array($record['message'])) {
                $message = implode(', ', array_map('strval', $record['message']));
            }
        }
        $code = isset($record['code']) && is_string($record['code']) ? $record['code'] : null;
        $errors = $record['errors'] ?? null;
        $retryAfter = $this->parseRetryAfter($responseHeaders['retry-after'] ?? null);
        $requestId = $responseHeaders['x-request-id'] ?? $responseHeaders['x-correlation-id'] ?? null;

        $class = $this->exceptionClassForStatus($status);
        return new $class($status, $message, $code, $errors, $body, $retryAfter, $requestId);
    }

    /**
     * @return class-string<PageWeaverAPIException>
     */
    private function exceptionClassForStatus(int $status): string
    {
        switch ($status) {
            case 400:
            case 422:
                return PageWeaverValidationException::class;
            case 401:
                return PageWeaverAuthenticationException::class;
            case 402:
                return PageWeaverPlanRequiredException::class;
            case 403:
                return PageWeaverPermissionException::class;
            case 404:
                return PageWeaverNotFoundException::class;
            case 409:
                return PageWeaverConflictException::class;
            case 429:
                return PageWeaverRateLimitException::class;
            default:
                return $status >= 500 ? PageWeaverServerException::class : PageWeaverAPIException::class;
        }
    }

    private function backoffDelayMs(int $attempt, ?float $retryAfterSeconds = null): float
    {
        if ($retryAfterSeconds !== null) {
            return $retryAfterSeconds * 1000.0;
        }
        $backoff = min($this->retry['baseDelayMs'] * (2 ** $attempt), $this->retry['maxDelayMs']);
        return $backoff / 2 + (mt_rand() / mt_getrandmax()) * ($backoff / 2);
    }

    private function sleepMs(float $ms): void
    {
        if ($ms > 0) {
            usleep((int) ($ms * 1000));
        }
    }

    private function parseRetryAfter(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }
        $when = strtotime($value);
        if ($when !== false) {
            return max(0.0, (float) ($when - time()));
        }
        return null;
    }

    /**
     * @param array<string,string|int|float|null> $query
     */
    private function buildQuery(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $pairs[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        if ($pairs === []) {
            return '';
        }
        return '?' . http_build_query($pairs);
    }

    /**
     * @return array<string,string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        // The last header block wins (in case of redirects with CURLOPT_FOLLOWLOCATION).
        $blocks = preg_split("/\r\n\r\n/", trim($rawHeaders));
        $lastBlock = is_array($blocks) && $blocks !== [] ? (string) end($blocks) : $rawHeaders;
        foreach (preg_split("/\r\n|\n/", $lastBlock) ?: [] as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $headers[$name] = $value;
        }
        return $headers;
    }
}
