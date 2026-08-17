<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * Read-only discovery of workflow definitions (the stage graph / transitions / task templates a
 * workflow compiles to). No public write route yet — authoring is via `deploy` / documents-as-code
 * or the portal designer. Requires the `workflows:read` scope.
 */
class WorkflowDefinitions
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
        return $this->http->json('GET', '/v1/workflow-definitions', null, [
            'status' => $params['status'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/workflow-definitions/' . rawurlencode($id));
    }

    /**
     * @param array<string,mixed> $params cursor, limit
     * @return array<string,mixed>
     */
    public function versions(string $id, array $params = []): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/workflow-definitions/' . rawurlencode($id) . '/versions', null, [
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /** @return array<string,mixed> */
    public function version(string $id, int $version): array
    {
        Validation::requireId($id, 'id');
        Validation::requirePositiveInt($version, 'version');
        return $this->http->json('GET', '/v1/workflow-definitions/' . rawurlencode($id) . '/versions/' . rawurlencode((string) $version));
    }
}
