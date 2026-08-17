<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * Full-text search across objects and documents, permission-trimmed: a hit the caller may not view
 * is silently dropped, never surfaced as a 403 (avoids confirming a hidden record exists). Requires
 * the `search:read` scope; object hits are additionally gated by `objects:read`.
 */
class Search
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * `params.q` is required and uses websearch syntax: quote a phrase, `-exclude`, `OR`.
     *
     * @param array<string,mixed> $params q, subjectType, objectTypeKey, classification, ownerUserId, updatedAfter, updatedBefore, cursor, limit
     * @return array<string,mixed>
     */
    public function query(array $params): array
    {
        Validation::requireString($params['q'] ?? null, 'params.q');
        return $this->http->json('GET', '/v1/search', null, [
            'q' => $params['q'],
            'subjectType' => $params['subjectType'] ?? null,
            'objectTypeKey' => $params['objectTypeKey'] ?? null,
            'classification' => $params['classification'] ?? null,
            'ownerUserId' => $params['ownerUserId'] ?? null,
            'updatedAfter' => $params['updatedAfter'] ?? null,
            'updatedBefore' => $params['updatedBefore'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }
}
