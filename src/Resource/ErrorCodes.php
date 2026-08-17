<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * The public, unauthenticated catalog of every coded API failure (`GET /v1/errors`): the HTTP
 * status each code always answers with, plus a cause/resolution pair. Build typed handling around
 * {@link \PageWeaver\PageWeaverAPIException::$code} against this instead of hardcoding strings,
 * since status codes are shared across many failure kinds but `code` is unique per cause. Requires
 * no API key (like `/openapi.json`).
 */
class ErrorCodes
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->http->json('GET', '/v1/errors', null, [], [], true);
    }
}
