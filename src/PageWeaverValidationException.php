<?php

namespace PageWeaver;

/**
 * `400` / `422` — the request body or query failed validation. `errors` carries the field-level
 * detail when present.
 */
class PageWeaverValidationException extends PageWeaverAPIException
{
}
