<?php

namespace PageWeaver;

/**
 * The API returned a non-2xx response. `status` is the HTTP status; `code`/`errors` are
 * pulled from the JSON body when present (e.g. payload validation errors on a 400), and
 * `body` is the raw parsed body for anything the typed fields don't cover.
 *
 * Prefer catching one of the specific subclasses ({@link PageWeaverValidationException},
 * {@link PageWeaverAuthenticationException}, {@link PageWeaverPlanRequiredException},
 * {@link PageWeaverPermissionException}, {@link PageWeaverNotFoundException},
 * {@link PageWeaverConflictException}, {@link PageWeaverRateLimitException},
 * {@link PageWeaverServerException}) when you want to branch on the failure kind; every one of
 * them is also a `PageWeaverAPIException`, so a single `catch (PageWeaverAPIException $e)` still
 * catches all of them.
 */
class PageWeaverAPIException extends PageWeaverException
{
    /** @var int */
    public $status;

    /** @var string|null */
    public $code;

    /** @var mixed */
    public $errors;

    /** @var mixed */
    public $body;

    /** `Retry-After` response header, seconds, when present (429/503). @var float|null */
    public $retryAfterSeconds;

    /** The account-scoped `X-Request-Id`/correlation id, when the API sent one, for support tickets. @var string|null */
    public $requestId;

    /**
     * @param mixed  $errors
     * @param mixed  $body
     */
    public function __construct(
        int $status,
        string $message,
        ?string $code = null,
        $errors = null,
        $body = null,
        ?float $retryAfterSeconds = null,
        ?string $requestId = null
    ) {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->code = $code;
        $this->errors = $errors;
        $this->body = $body;
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->requestId = $requestId;
    }

    /** Whether retrying this exact request (with the same idempotency key, if any) may succeed. */
    public function isRetryable(): bool
    {
        return $this->status === 429 || $this->status >= 500;
    }
}
