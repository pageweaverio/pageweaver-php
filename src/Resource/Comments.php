<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * Anchored comment threads on rendered documents: create, list, reply, and lifecycle
 * (resolve / reopen / close). Requires a `review`-scoped key for writes.
 */
class Comments
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Create an anchored thread with its first message. Returns `201`.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->json('POST', '/v1/comments', $body);
    }

    /**
     * List a document's threads, newest first.
     *
     * @param array<string,mixed> $params pageNumber, status, severity, cursor, limit
     * @return array<string,mixed>
     */
    public function list(string $documentId, array $params = []): array
    {
        return $this->http->json('GET', '/v1/documents/' . rawurlencode($documentId) . '/comments', null, [
            'pageNumber' => $params['pageNumber'] ?? null,
            'status' => $params['status'] ?? null,
            'severity' => $params['severity'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Fetch one thread with its full message list.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->json('GET', '/v1/comments/' . rawurlencode($id));
    }

    /**
     * Edit severity, assignment, due date, or relocate the anchor coordinates.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->json('PATCH', '/v1/comments/' . rawurlencode($id), $body);
    }

    /**
     * Reply on a thread. Returns `201`.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function reply(string $id, array $body): array
    {
        return $this->http->json('POST', '/v1/comments/' . rawurlencode($id) . '/messages', $body);
    }

    /**
     * Resolve a thread (open -> resolved).
     *
     * @return array<string,mixed>
     */
    public function resolve(string $id): array
    {
        return $this->http->json('POST', '/v1/comments/' . rawurlencode($id) . '/resolve');
    }

    /**
     * Reopen a resolved thread (resolved -> open).
     *
     * @return array<string,mixed>
     */
    public function reopen(string $id): array
    {
        return $this->http->json('POST', '/v1/comments/' . rawurlencode($id) . '/reopen');
    }

    /**
     * Close a thread permanently (-> closed, final).
     *
     * @return array<string,mixed>
     */
    public function close(string $id): array
    {
        return $this->http->json('POST', '/v1/comments/' . rawurlencode($id) . '/close');
    }
}
