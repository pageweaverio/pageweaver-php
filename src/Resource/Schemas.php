<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/** Read-only discovery of the JSON Schemas your payloads validate against. */
class Schemas
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * All schemas owned by the key's account, newest-updated first.
     *
     * @return array<int,mixed>
     */
    public function list(): array
    {
        return $this->http->json('GET', '/v1/schemas');
    }

    /**
     * A schema's published JSON Schema plus a derived sample, for the latest published version or a
     * specific `version`.
     *
     * @return array<string,mixed>
     */
    public function get(string $id, ?int $version = null): array
    {
        return $this->http->json('GET', '/v1/schemas/' . rawurlencode($id), null, ['version' => $version]);
    }

    /**
     * A schema's published version history (newest first).
     *
     * @return array<int,mixed>
     */
    public function versions(string $id): array
    {
        return $this->http->json('GET', '/v1/schemas/' . rawurlencode($id) . '/versions');
    }

    /**
     * One published version's metadata, plus its frozen FieldNode tree when `include: "nodes"`.
     *
     * @return array<string,mixed>
     */
    public function version(string $id, int $version, ?string $include = null): array
    {
        return $this->http->json(
            'GET',
            '/v1/schemas/' . rawurlencode($id) . '/versions/' . rawurlencode((string) $version),
            null,
            ['include' => $include]
        );
    }
}
