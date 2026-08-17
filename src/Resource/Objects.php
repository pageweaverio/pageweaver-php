<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * Typed business records: the values held under an {@link ObjectTypes} type. Reads need
 * `objects:read` (plus `objects:read-sensitive` to decrypt sensitive fields); writes need
 * `objects:write`; relationships and document links need `relationships:manage`.
 */
class Objects
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * List objects. Rows never carry field data — {@link get} one for that.
     *
     * @param array<string,mixed> $params objectTypeKey, objectTypeId, status, lifecycleState, ownerUserId, number, cursor, limit
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/objects', null, [
            'objectTypeKey' => $params['objectTypeKey'] ?? null,
            'objectTypeId' => $params['objectTypeId'] ?? null,
            'status' => $params['status'] ?? null,
            'lifecycleState' => $params['lifecycleState'] ?? null,
            'ownerUserId' => $params['ownerUserId'] ?? null,
            'number' => $params['number'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Fetch one object's current (or a specific `version`'s) value. Pass `includeSensitive: true` to
     * decrypt sensitive fields (requires the `objects:read-sensitive` scope; a key without it gets a
     * 403, never a silently redacted response).
     *
     * @param array<string,mixed> $opts version, includeSensitive
     * @return array<string,mixed>
     */
    public function get(string $id, array $opts = []): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/objects/' . rawurlencode($id), null, [
            'version' => $opts['version'] ?? null,
            'includeSensitive' => !empty($opts['includeSensitive']) ? 'true' : null,
        ]);
    }

    /**
     * Create an object. Provide exactly one of `objectTypeKey`/`objectTypeId`. Pass `idempotencyKey` to
     * make a retried create return the original record instead of creating a duplicate (sent as the
     * `Idempotency-Key` header); the same key with a different body is a 409.
     *
     * @param array<string,mixed> $params objectTypeKey|objectTypeId, data, idempotencyKey
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        Validation::requireObjectBody($params, 'params');
        Validation::requireOneOf(
            $params['objectTypeKey'] ?? null,
            'objectTypeKey',
            $params['objectTypeId'] ?? null,
            'objectTypeId'
        );
        Validation::requireObjectBody($params['data'] ?? null, 'params.data');
        $idempotencyKey = $params['idempotencyKey'] ?? null;
        unset($params['idempotencyKey']);
        $headers = $idempotencyKey !== null ? ['idempotency-key' => (string) $idempotencyKey] : [];
        return $this->http->json('POST', '/v1/objects', $params, [], $headers);
    }

    /**
     * Replace an object's whole value (never merged). `expectedVersion` is required — an optimistic
     * concurrency check the API enforces with a 409 on mismatch, so a lost update never overwrites
     * someone else's change silently.
     *
     * @param array<string,mixed> $params data, expectedVersion
     * @return array<string,mixed>
     */
    public function replace(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireObjectBody($params, 'params');
        Validation::requireObjectBody($params['data'] ?? null, 'params.data');
        Validation::requirePositiveInt($params['expectedVersion'] ?? null, 'params.expectedVersion');
        return $this->http->json('PUT', '/v1/objects/' . rawurlencode($id), $params, [], [
            'if-match' => (string) $params['expectedVersion'],
        ]);
    }

    /**
     * Version history (metadata only, never values — read a version's value via {@link get} + `version`).
     *
     * @param array<string,mixed> $params cursor, limit
     * @return array<string,mixed>
     */
    public function versions(string $id, array $params = []): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/objects/' . rawurlencode($id) . '/versions', null, [
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Archive an object (reversible via {@link restore}).
     *
     * @param array<string,mixed> $params reason
     * @return array<string,mixed>
     */
    public function archive(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireString($params['reason'] ?? null, 'params.reason');
        return $this->http->json('POST', '/v1/objects/' . rawurlencode($id) . '/archive', $params);
    }

    /**
     * Restore an archived object. No new version is created — status only.
     *
     * @return array<string,mixed>
     */
    public function restore(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('POST', '/v1/objects/' . rawurlencode($id) . '/restore');
    }

    /**
     * List relationship edges to/from this object, in both directions. Pass `includeEnded` to include
     * ended ones.
     *
     * @param array<string,mixed> $opts includeEnded
     * @return array<int,mixed>
     */
    public function relationships(string $id, array $opts = []): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/objects/' . rawurlencode($id) . '/relationships', null, [
            'includeEnded' => !empty($opts['includeEnded']) ? 'true' : null,
        ]);
    }

    /**
     * Create a relationship from this object (the source) to `params.targetObjectId`. Provide exactly
     * one of `relationshipTypeKey`/`relationshipTypeId`. Refused (with a reason) when the endpoint
     * types aren't allowed, cardinality is already satisfied, either record is archived, or the target
     * is in a different account. `unchanged: true` on the result means an identical live edge already
     * existed.
     *
     * @param array<string,mixed> $params relationshipTypeKey|relationshipTypeId, targetObjectId
     * @return array<string,mixed>
     */
    public function addRelationship(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireObjectBody($params, 'params');
        Validation::requireOneOf(
            $params['relationshipTypeKey'] ?? null,
            'relationshipTypeKey',
            $params['relationshipTypeId'] ?? null,
            'relationshipTypeId'
        );
        Validation::requireString($params['targetObjectId'] ?? null, 'params.targetObjectId');
        return $this->http->json('POST', '/v1/objects/' . rawurlencode($id) . '/relationships', $params);
    }

    /**
     * End a relationship. The row stays (with `validTo` set); nothing is deleted.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function endRelationship(string $id, string $relationshipId, array $params = []): array
    {
        Validation::requireId($id, 'id');
        Validation::requireId($relationshipId, 'relationshipId');
        return $this->http->json(
            'POST',
            '/v1/objects/' . rawurlencode($id) . '/relationships/' . rawurlencode($relationshipId) . '/end',
            $params
        );
    }

    /**
     * List documents filed against this object.
     *
     * @return array<int,mixed>
     */
    public function documents(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/objects/' . rawurlencode($id) . '/documents');
    }

    /**
     * File a document against this object. Idempotent per `(document, object, role)`; default role
     * `"primary"`.
     *
     * @param array<string,mixed> $params documentId, role
     * @return array<string,mixed>
     */
    public function linkDocument(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireObjectBody($params, 'params');
        Validation::requireString($params['documentId'] ?? null, 'params.documentId');
        return $this->http->json('POST', '/v1/objects/' . rawurlencode($id) . '/documents', $params);
    }

    /**
     * Unfile a document link. Idempotent: unlinking an absent link succeeds with `removed: false`.
     *
     * @param array<string,mixed> $opts role
     * @return array<string,mixed>
     */
    public function unlinkDocument(string $id, string $documentId, array $opts = []): array
    {
        Validation::requireId($id, 'id');
        Validation::requireId($documentId, 'documentId');
        return $this->http->json(
            'DELETE',
            '/v1/objects/' . rawurlencode($id) . '/documents/' . rawurlencode($documentId),
            null,
            ['role' => $opts['role'] ?? null]
        );
    }
}
