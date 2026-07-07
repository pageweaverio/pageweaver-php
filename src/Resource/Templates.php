<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/** Read-only discovery of your published templates and their pinnable versions. */
class Templates
{
    /** @var Http */
    private $http;

    /**
     * Template change proposals (requires a `deploy`-scoped key).
     *
     * @var Proposals
     */
    public $proposals;

    public function __construct(Http $http)
    {
        $this->http = $http;
        $this->proposals = new Proposals($http);
    }

    /**
     * All templates owned by the key's account, newest-updated first.
     *
     * @return array<int,mixed>
     */
    public function list(): array
    {
        return $this->http->json('GET', '/v1/templates');
    }

    /**
     * One template's metadata (name, current version, associated schema, authoring mode).
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->json('GET', '/v1/templates/' . rawurlencode($id));
    }

    /**
     * A template's published version history (newest first).
     *
     * @return array<int,mixed>
     */
    public function versions(string $id): array
    {
        return $this->http->json('GET', '/v1/templates/' . rawurlencode($id) . '/versions');
    }

    /**
     * One published version's metadata, plus its frozen editor source when `include: "source"`.
     *
     * @return array<string,mixed>
     */
    public function version(string $id, int $version, ?string $include = null): array
    {
        return $this->http->json(
            'GET',
            '/v1/templates/' . rawurlencode($id) . '/versions/' . rawurlencode((string) $version),
            null,
            ['include' => $include]
        );
    }
}
