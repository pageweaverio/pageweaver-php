<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * Review requests on documents: create, list, add participants, and collect approvals against a
 * completion policy. Requires a `review`-scoped key for writes.
 */
class Reviews
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Open a review on a document with an optional policy + participants. Returns `201`.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->json('POST', '/v1/reviews', $body);
    }

    /**
     * List reviews, newest first. Filter by `status`/`documentId`; page with `cursor`.
     *
     * @param array<string,mixed> $params status, documentId, cursor, limit
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/reviews', null, [
            'status' => $params['status'] ?? null,
            'documentId' => $params['documentId'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Fetch one review with its participants, approvals, and computed policy state.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->json('GET', '/v1/reviews/' . rawurlencode($id));
    }

    /**
     * Add a participant (member `userId`, or `externalEmail` + `externalName`) with a role.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function addParticipant(string $id, array $body): array
    {
        return $this->http->json('POST', '/v1/reviews/' . rawurlencode($id) . '/participants', $body);
    }

    /**
     * Record an approval decision. Returns `201`; the review auto-completes when its policy is satisfied.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function approve(string $id, array $body): array
    {
        return $this->http->json('POST', '/v1/reviews/' . rawurlencode($id) . '/approvals', $body);
    }

    /**
     * Manually complete a review (policy-satisfied, or forced by an admin).
     *
     * @return array<string,mixed>
     */
    public function complete(string $id): array
    {
        return $this->http->json('POST', '/v1/reviews/' . rawurlencode($id) . '/complete');
    }

    /**
     * Withdraw a review (open -> canceled).
     *
     * @return array<string,mixed>
     */
    public function cancel(string $id): array
    {
        return $this->http->json('POST', '/v1/reviews/' . rawurlencode($id) . '/cancel');
    }
}
