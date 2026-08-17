<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * First-class document ingestion: bring in a PDF you already have (not a template render). Small
 * files use {@link create} directly; larger ones use a resumable chunked {@link $sessions} upload.
 * Every route requires the `documents:upload` scope.
 */
class Intake
{
    /** @var Http */
    private $http;

    /** Resumable chunked upload sessions. @var IntakeSessions */
    public $sessions;

    public function __construct(Http $http)
    {
        $this->http = $http;
        $this->sessions = new IntakeSessions($http);
    }

    /**
     * Synchronously ingest one PDF (multipart). Returns `202`.
     *
     * @param array<string,mixed> $params objectId, objectRole, classification,
     *     file: array{data:string,filename:string,contentType?:string|null}
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        Validation::requireObjectBody($params, 'params');
        $file = $params['file'] ?? [];
        return $this->http->multipart(
            'POST',
            '/v1/documents/intake',
            [
                'objectId' => $params['objectId'] ?? null,
                'objectRole' => $params['objectRole'] ?? null,
                'classification' => $params['classification'] ?? null,
            ],
            [
                'file' => [
                    'data' => (string) ($file['data'] ?? ''),
                    'filename' => (string) ($file['filename'] ?? 'document.pdf'),
                    'contentType' => $file['contentType'] ?? null,
                ],
            ]
        );
    }
}
