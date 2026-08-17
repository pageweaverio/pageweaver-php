<?php

namespace PageWeaver;

/**
 * `409` — an optimistic-concurrency mismatch (`expectedVersion`/`If-Match`), a duplicate key, or a
 * state conflict.
 */
class PageWeaverConflictException extends PageWeaverAPIException
{
}
