<?php

namespace PageWeaver;

/**
 * The document reached the terminal `failed` state while waiting (or was not downloadable). Thrown by
 * `waitFor`/`createAndWait`/`download` unless `throwOnFailure` is false. `document` carries the final
 * response (including its `error` string).
 */
class PageWeaverDocumentFailedException extends PageWeaverException
{
    /** @var array<string,mixed> */
    public $document;

    /**
     * @param array<string,mixed> $document
     */
    public function __construct(array $document)
    {
        $id = isset($document['id']) && is_string($document['id']) ? $document['id'] : 'unknown';
        $error = isset($document['error']) && is_string($document['error']) ? $document['error'] : 'unknown error';
        parent::__construct("Document {$id} failed: {$error}");
        $this->document = $document;
    }
}
