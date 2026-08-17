<?php

namespace PageWeaver;

/**
 * `5xx` — the API failed unexpectedly. Safe to retry (the HTTP client already retries these
 * automatically).
 */
class PageWeaverServerException extends PageWeaverAPIException
{
}
