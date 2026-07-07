<?php

namespace PageWeaver;

/**
 * The API returned a non-2xx response. `status` is the HTTP status; `code`/`errors` are
 * pulled from the JSON body when present (e.g. payload validation errors on a 400), and
 * `body` is the raw parsed body for anything the typed fields don't cover.
 */
class PageWeaverApiException extends PageWeaverException
{
    /** @var int */
    public $status;

    /** @var string|null */
    public $code;

    /** @var mixed */
    public $errors;

    /** @var mixed */
    public $body;

    /**
     * @param mixed  $errors
     * @param mixed  $body
     */
    public function __construct(int $status, string $message, ?string $code = null, $errors = null, $body = null)
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->code = $code;
        $this->errors = $errors;
        $this->body = $body;
    }
}
