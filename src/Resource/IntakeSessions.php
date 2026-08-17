<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * Resumable chunked uploads: start a session, upload each chunk, then finalize. Sessions expire 24h
 * after creation. Reached as `$client->intake->sessions`.
 */
class IntakeSessions
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Start a resumable upload session. `chunkSize` is capped at 10 MiB by the API. Returns `201`.
     *
     * @param array<string,mixed> $params filename, totalBytes, chunkSize, ...
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        Validation::requireObjectBody($params, 'params');
        return $this->http->json('POST', '/v1/documents/intake/sessions', $params);
    }

    /**
     * Start up to 200 resumable sessions at once (bulk import). Partial failure is expected: each
     * file's outcome is reported individually.
     *
     * @param array<string,mixed> $params files: array<int,array<string,mixed>>
     * @return array<string,mixed>
     */
    public function createBatch(array $params): array
    {
        Validation::requireObjectBody($params, 'params');
        Validation::requireNonEmptyArray($params['files'] ?? null, 'params.files');
        return $this->http->json('POST', '/v1/documents/intake/sessions/batch', $params);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/documents/intake/sessions/' . rawurlencode($id));
    }

    /**
     * Abandon a session and delete its staged chunks. A `done` session cannot be abandoned.
     *
     * @return array<string,mixed>
     */
    public function abandon(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('DELETE', '/v1/documents/intake/sessions/' . rawurlencode($id));
    }

    /**
     * Upload one 0-based chunk (multipart). Idempotent: re-sending an already-received index is a
     * no-op success.
     *
     * @return array<string,mixed>
     */
    public function uploadChunk(string $id, int $index, string $chunkData): array
    {
        Validation::requireId($id, 'id');
        Validation::requireNonNegativeInt($index, 'index');
        return $this->http->multipart(
            'PUT',
            '/v1/documents/intake/sessions/' . rawurlencode($id) . '/chunks/' . rawurlencode((string) $index),
            [],
            ['chunk' => ['data' => $chunkData, 'filename' => 'chunk']]
        );
    }

    /**
     * Finalize a session once every chunk has arrived. A single-file/PDF session resolves to an
     * intake result; a ZIP session expands into many documents. Returns `202`.
     *
     * @return array<string,mixed>
     */
    public function finalize(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('POST', '/v1/documents/intake/sessions/' . rawurlencode($id) . '/finalize');
    }
}
