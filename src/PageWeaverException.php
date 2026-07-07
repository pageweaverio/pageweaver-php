<?php

namespace PageWeaver;

/**
 * Raised when the API returns a non-2xx response, or the request fails.
 */
class PageWeaverException extends \RuntimeException
{
    /** @var int|null */
    public $status;

    /** @var mixed */
    public $body;

    /**
     * @param mixed $body
     */
    public function __construct(string $message, ?int $status = null, $body = null)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->body = $body;
    }
}
