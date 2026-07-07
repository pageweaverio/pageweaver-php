<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * Deployments: documents-as-code. Plan a `pageweaver.yml` manifest against a target environment, then
 * apply it. Plan and apply are separate, explicit calls. Writes require a `deploy`-scoped key.
 */
class Deployments
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Plan a deployment: send the manifest text + the files it names + the target environment. Returns
     * `202` with the plan. Nothing is applied.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function plan(array $body, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey !== null ? ['Idempotency-Key' => $idempotencyKey] : [];
        return $this->http->json('POST', '/v1/deployments/plan', $body, [], $headers);
    }

    /**
     * Recent deployments for the account, newest first. Filter by `environment` slug.
     *
     * @param array<string,mixed> $params environment, limit
     * @return array<int,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/deployments', null, [
            'environment' => $params['environment'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * One deployment with its per-resource plan lines and their apply outcomes.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->json('GET', '/v1/deployments/' . rawurlencode($id));
    }

    /**
     * Apply a planned deployment. Returns `202` with the deployment in `applying`.
     *
     * @return array<string,mixed>
     */
    public function apply(string $id): array
    {
        return $this->http->json('POST', '/v1/deployments/' . rawurlencode($id) . '/apply');
    }
}
