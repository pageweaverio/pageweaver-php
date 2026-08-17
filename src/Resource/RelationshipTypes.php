<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * Edge-rule definitions between object types (key, label/inverseLabel, allowed source/target types,
 * cardinality). Deliberately not versioned — nothing is ever validated against a frozen snapshot of
 * one. Reads need `objects:read`; writes need `relationships:manage`.
 */
class RelationshipTypes
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * @param array<string,mixed> $params status, cursor, limit
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/relationship-types', null, [
            'status' => $params['status'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/relationship-types/' . rawurlencode($id));
    }

    /**
     * `inverseLabel` is required: relationships read in both directions (source -> target,
     * target -> source).
     *
     * @param array<string,mixed> $params key, label, inverseLabel, ...
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        Validation::requireObjectBody($params, 'params');
        Validation::requireString($params['key'] ?? null, 'params.key');
        Validation::requireString($params['label'] ?? null, 'params.label');
        Validation::requireString($params['inverseLabel'] ?? null, 'params.inverseLabel');
        return $this->http->json('POST', '/v1/relationship-types', $params);
    }

    /**
     * Changes govern only edges created AFTER the update; existing edges are never re-checked or
     * removed.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function update(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireObjectBody($params, 'params');
        return $this->http->json('PATCH', '/v1/relationship-types/' . rawurlencode($id), $params);
    }

    /**
     * Deprecate a relationship type. No delete — existing edges of this type are untouched.
     *
     * @param array<string,mixed> $params reason
     * @return array<string,mixed>
     */
    public function deprecate(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireString($params['reason'] ?? null, 'params.reason');
        return $this->http->json('POST', '/v1/relationship-types/' . rawurlencode($id) . '/deprecate', $params);
    }
}
