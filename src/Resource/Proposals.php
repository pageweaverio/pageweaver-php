<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * Template proposals: the PR analog for template changes. Reached as `$client->templates->proposals`,
 * scoped to a template id passed on each call. All writes require a `deploy`-scoped API key.
 */
class Proposals
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Open a proposal on a template: freeze a candidate. Returns `202` with the proposal.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function open(string $templateId, array $body = []): array
    {
        return $this->http->json('POST', '/v1/templates/' . rawurlencode($templateId) . '/proposals', $body);
    }

    /**
     * List a template's proposals, newest first. Filter by `status`; page with `cursor`.
     *
     * @param array<string,mixed> $params status, cursor, limit
     * @return array<string,mixed>
     */
    public function list(string $templateId, array $params = []): array
    {
        return $this->http->json('GET', '/v1/templates/' . rawurlencode($templateId) . '/proposals', null, [
            'status' => $params['status'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Fetch one proposal with its check summary, approvals, and promote-gate state.
     *
     * @return array<string,mixed>
     */
    public function get(string $templateId, string $proposalId): array
    {
        return $this->http->json(
            'GET',
            '/v1/templates/' . rawurlencode($templateId) . '/proposals/' . rawurlencode($proposalId)
        );
    }

    /**
     * Re-run the render-diff regression (candidate vs. the live version, per dataset). Returns `202`.
     *
     * @return array<string,mixed>
     */
    public function rerunChecks(string $templateId, string $proposalId): array
    {
        return $this->http->json(
            'POST',
            '/v1/templates/' . rawurlencode($templateId) . '/proposals/' . rawurlencode($proposalId) . '/checks'
        );
    }

    /**
     * Append an approval decision. Returns `201`.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function approve(string $templateId, string $proposalId, array $body = []): array
    {
        return $this->http->json(
            'POST',
            '/v1/templates/' . rawurlencode($templateId) . '/proposals/' . rawurlencode($proposalId) . '/approve',
            $body
        );
    }

    /**
     * Append a rejection decision. Returns `201`.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function reject(string $templateId, string $proposalId, array $body = []): array
    {
        return $this->http->json(
            'POST',
            '/v1/templates/' . rawurlencode($templateId) . '/proposals/' . rawurlencode($proposalId) . '/reject',
            $body
        );
    }

    /**
     * Promote the candidate: publish it as the next version. Fails (`409`) when the gate is unmet.
     *
     * @return array<string,mixed>
     */
    public function promote(string $templateId, string $proposalId): array
    {
        return $this->http->json(
            'POST',
            '/v1/templates/' . rawurlencode($templateId) . '/proposals/' . rawurlencode($proposalId) . '/promote'
        );
    }

    /**
     * Withdraw an open proposal (only while open). The live version is untouched.
     *
     * @return array<string,mixed>
     */
    public function retract(string $templateId, string $proposalId): array
    {
        return $this->http->json(
            'DELETE',
            '/v1/templates/' . rawurlencode($templateId) . '/proposals/' . rawurlencode($proposalId)
        );
    }
}
