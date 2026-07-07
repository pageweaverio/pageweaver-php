<?php

namespace PageWeaver;

/**
 * Internal HTTP client: attaches the API key, serializes JSON, applies a timeout, and maps non-2xx
 * responses to {@link PageWeaverApiException} and transport failures to {@link PageWeaverConnectionException}.
 * Every resource is built on this. Not part of the public API.
 *
 * @internal
 */
class Http
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $timeout;

    public function __construct(string $apiKey, string $baseUrl, int $timeout)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
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
    public function json(string $method, string $path, ?array $body = null, array $query = [], array $headers = []): array
    {
        $res = $this->send($method, $path, $body, $query, $headers, false);
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
            throw new PageWeaverApiException(
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
            throw $this->toApiError($status, $responseBody);
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
    }

    private function toApiError(int $status, string $rawBody): PageWeaverApiException
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

        return new PageWeaverApiException($status, $message, $code, $errors, $body);
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
