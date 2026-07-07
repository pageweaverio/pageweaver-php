<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * Environments & pins: a named per-account pointer set over immutable template versions. Writes
 * require a `deploy`-scoped API key; reads need `read`.
 */
class Environments
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Every environment for the account, with pin counts.
     *
     * @return array<int,mixed>
     */
    public function list(): array
    {
        return $this->http->json('GET', '/v1/environments');
    }

    /**
     * Create a named pointer set (e.g. staging / production). Returns `201`.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->json('POST', '/v1/environments', $body);
    }

    /**
     * Fetch one environment by slug.
     *
     * @return array<string,mixed>
     */
    public function get(string $slug): array
    {
        return $this->http->json('GET', '/v1/environments/' . rawurlencode($slug));
    }

    /**
     * Rename an environment or flip its production flag. The slug is immutable.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function update(string $slug, array $body): array
    {
        return $this->http->json('PATCH', '/v1/environments/' . rawurlencode($slug), $body);
    }

    /**
     * Delete an environment and its pins (audited).
     *
     * @return array<string,mixed>
     */
    public function delete(string $slug): array
    {
        return $this->http->json('DELETE', '/v1/environments/' . rawurlencode($slug));
    }

    /**
     * The template -> version pointers in an environment.
     *
     * @return array<int,mixed>
     */
    public function pins(string $slug): array
    {
        return $this->http->json('GET', '/v1/environments/' . rawurlencode($slug) . '/pins');
    }

    /**
     * Point a template at one of its published versions (creates or moves the pin).
     *
     * @return array<string,mixed>
     */
    public function setPin(string $slug, string $templateId, int $version): array
    {
        return $this->http->json(
            'PUT',
            '/v1/environments/' . rawurlencode($slug) . '/pins/' . rawurlencode($templateId),
            ['version' => $version]
        );
    }

    /**
     * Unpin a template from an environment.
     *
     * @return array<string,mixed>
     */
    public function removePin(string $slug, string $templateId): array
    {
        return $this->http->json(
            'DELETE',
            '/v1/environments/' . rawurlencode($slug) . '/pins/' . rawurlencode($templateId)
        );
    }

    /**
     * Copy another environment's pin set onto this one (e.g. staging -> production).
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function promote(string $slug, array $body): array
    {
        return $this->http->json('POST', '/v1/environments/' . rawurlencode($slug) . '/promote', $body);
    }

    /**
     * Roll an environment back to a prior deployment's pin set (a NEW pin-only deployment).
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function rollback(string $slug, array $body = []): array
    {
        return $this->http->json('POST', '/v1/environments/' . rawurlencode($slug) . '/rollback', $body);
    }
}
