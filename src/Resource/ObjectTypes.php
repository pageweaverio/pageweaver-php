<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * Typed business-record definitions: draft + immutable-published-version model, mirroring template
 * versioning. Reads need the `objects:read` scope; writes need `object-types:manage`. See
 * {@link Objects} for the records themselves.
 */
class ObjectTypes
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * List object types owned by the key's account.
     *
     * @param array<string,mixed> $params status, cursor, limit
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/object-types', null, [
            'status' => $params['status'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Fetch one object type's current view plus its draft (unpublished working) artifact.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/object-types/' . rawurlencode($id));
    }

    /**
     * Create an object type draft. `key` is immutable once set; publish it with {@link publish}.
     *
     * @param array<string,mixed> $params key, nameSingular, namePlural, schema, ...
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        Validation::requireObjectBody($params, 'params');
        Validation::requireString($params['key'] ?? null, 'params.key');
        Validation::requireString($params['nameSingular'] ?? null, 'params.nameSingular');
        Validation::requireString($params['namePlural'] ?? null, 'params.namePlural');
        return $this->http->json('POST', '/v1/object-types', $params);
    }

    /**
     * Edit the draft. Any field omitted is left unchanged; editing clears `hasUnpublishedChanges`'s
     * prior hash.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function update(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireObjectBody($params, 'params');
        return $this->http->json('PATCH', '/v1/object-types/' . rawurlencode($id), $params);
    }

    /**
     * List published (immutable) versions, newest first.
     *
     * @param array<string,mixed> $params cursor, limit
     * @return array<string,mixed>
     */
    public function versions(string $id, array $params = []): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/object-types/' . rawurlencode($id) . '/versions', null, [
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Fetch one immutable published version, including its compiled field policies.
     *
     * @return array<string,mixed>
     */
    public function version(string $id, int $version): array
    {
        Validation::requireId($id, 'id');
        Validation::requirePositiveInt($version, 'version');
        return $this->http->json('GET', '/v1/object-types/' . rawurlencode($id) . '/versions/' . rawurlencode((string) $version));
    }

    /**
     * Publish the draft, freezing its schema + policies into a new immutable version. Republishing an
     * unchanged draft is a no-op: it returns the CURRENT version with `unchanged: true` (no new version
     * minted).
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function publish(string $id, array $params = []): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('POST', '/v1/object-types/' . rawurlencode($id) . '/publish', $params);
    }

    /**
     * Deprecate a type (idempotent no-op if already deprecated). Existing records are unaffected.
     *
     * @param array<string,mixed> $params reason
     * @return array<string,mixed>
     */
    public function deprecate(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireString($params['reason'] ?? null, 'params.reason');
        return $this->http->json('POST', '/v1/object-types/' . rawurlencode($id) . '/deprecate', $params);
    }
}
