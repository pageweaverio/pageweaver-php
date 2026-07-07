<?php

namespace PageWeaver;

/**
 * `waitFor`/`createAndWait` exceeded its timeout before the document reached a terminal state.
 */
class PageWeaverTimeoutException extends PageWeaverException
{
    /** @var string */
    public $documentId;

    /** @var string|null */
    public $lastStatus;

    public function __construct(string $documentId, ?string $lastStatus, float $timeoutMs)
    {
        parent::__construct(
            "Timed out after {$timeoutMs}ms waiting for document {$documentId} (last status: "
                . ($lastStatus ?? 'unknown') . ').'
        );
        $this->documentId = $documentId;
        $this->lastStatus = $lastStatus;
    }
}
