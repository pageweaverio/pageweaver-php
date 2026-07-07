<?php

namespace PageWeaver;

/**
 * Client for the PageWeaver document-generation API.
 */
class Client
{
    private const DEFAULT_BASE_URL = 'https://api.pageweaver.io';
    private const TERMINAL = ['done', 'failed', 'error'];

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $apiKey, string $baseUrl = self::DEFAULT_BASE_URL, int $timeout = 30)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * POST /v1/documents. Pass the request body per the API docs, e.g.
     * ['templateId' => '...', 'payload' => [...]] or ['html' => '...'].
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function createDocument(array $body): array
    {
        return $this->request('POST', '/v1/documents', $body);
    }

    /**
     * GET /v1/documents/:id.
     *
     * @return array<string,mixed>
     */
    public function getDocument(string $id): array
    {
        return $this->request('GET', '/v1/documents/' . rawurlencode($id));
    }

    /**
     * Create a document and poll until it reaches a terminal state.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function createAndWait(array $body, float $pollInterval = 1.0, float $timeout = 60.0): array
    {
        $created = $this->createDocument($body);
        $id = $created['id'] ?? null;
        if (!is_string($id) || $id === '') {
            return $created;
        }
        $deadline = microtime(true) + $timeout;
        while (true) {
            $doc = $this->getDocument($id);
            $status = $doc['status'] ?? null;
            if (is_string($status) && in_array($status, self::TERMINAL, true)) {
                return $doc;
            }
            if (microtime(true) >= $deadline) {
                throw new PageWeaverException("Timed out waiting for document {$id}");
            }
            usleep((int) ($pollInterval * 1000000));
        }
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'x-api-key: ' . $this->apiKey,
            'Accept: application/json',
        ];
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($body));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new PageWeaverException('HTTP request failed: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = $raw === '' ? [] : json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            throw new PageWeaverException(
                "{$method} {$path} failed with status {$status}",
                $status,
                is_array($decoded) ? $decoded : $raw
            );
        }
        return is_array($decoded) ? $decoded : [];
    }
}
