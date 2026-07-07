<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * Capability-scoped links that let people without an account view, comment on, or approve a document.
 * Requires a `review`-scoped key.
 */
class ShareLinks
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Create a share link. The response includes the raw `url` and `token` exactly once.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->json('POST', '/v1/share-links', $body);
    }

    /**
     * List active + disabled links (never the tokens). Filter by document or review.
     *
     * @param array<string,mixed> $params documentId, reviewRequestId
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/share-links', null, [
            'documentId' => $params['documentId'] ?? null,
            'reviewRequestId' => $params['reviewRequestId'] ?? null,
        ]);
    }

    /**
     * Disable a link immediately (the kill switch).
     *
     * @return array<string,mixed>
     */
    public function disable(string $id): array
    {
        return $this->http->json('POST', '/v1/share-links/' . rawurlencode($id) . '/disable');
    }
}
