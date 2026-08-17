<?php

namespace PageWeaver;

/**
 * `401` — the API key is missing, malformed, revoked, or the account is suspended/scheduled for
 * deletion.
 */
class PageWeaverAuthenticationException extends PageWeaverAPIException
{
}
