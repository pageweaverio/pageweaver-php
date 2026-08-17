<?php

namespace PageWeaver;

/**
 * `429` — rate limited or over a usage quota. `retryAfterSeconds` is set when the API sent
 * `Retry-After`.
 */
class PageWeaverRateLimitException extends PageWeaverAPIException
{
}
