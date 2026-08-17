<?php

namespace PageWeaver;

/**
 * A request body failed a client-side shape check before it was sent — no network call was made.
 * Fix the message; `path` names the offending field when known.
 */
class PageWeaverInvalidRequestException extends PageWeaverException
{
    /** @var string|null */
    public $path;

    public function __construct(string $message, ?string $path = null)
    {
        parent::__construct($message);
        $this->path = $path;
    }
}
