<?php

namespace PageWeaver;

/**
 * Thrown when a webhook signature does not match the body.
 */
class PageWeaverWebhookSignatureException extends PageWeaverException
{
    public function __construct(string $message = 'Invalid webhook signature.')
    {
        parent::__construct($message);
    }
}
