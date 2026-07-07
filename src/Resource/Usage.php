<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/** Your page consumption against the plan quota for the current billing period. */
class Usage
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Current-period usage: billable document pages and editor preview pages, with their limits.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->json('GET', '/v1/usage');
    }
}
