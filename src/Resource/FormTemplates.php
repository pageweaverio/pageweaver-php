<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\Validation;

/**
 * Fillable AcroForm templates: upload a PDF that already has its own form fields, then fill and
 * render it as a document. Distinct from Smart Forms (a Liquid template + JSON Schema). Uploads
 * need the `documents:upload` scope, reads need `read`, filling needs `render`.
 */
class FormTemplates
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Upload a PDF as a new fillable template. Scans, safety-checks, and enumerates its AcroForm
     * fields.
     *
     * @param array<string,mixed> $params name, description,
     *     file: array{data:string,filename:string,contentType?:string|null} raw PDF bytes
     * @return array<string,mixed>
     */
    public function create(array $params): array
    {
        Validation::requireObjectBody($params, 'params');
        Validation::requireString($params['name'] ?? null, 'params.name');
        return $this->http->multipart(
            'POST',
            '/v1/form-templates',
            ['name' => $params['name'], 'description' => $params['description'] ?? null],
            ['file' => $this->filePart($params['file'], 'template.pdf')]
        );
    }

    /** @return array<int,mixed> */
    public function list(): array
    {
        return $this->http->json('GET', '/v1/form-templates');
    }

    /**
     * Fetch a template plus its current version's derived field-schema contract.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/form-templates/' . rawurlencode($id));
    }

    /** @return array<int,mixed> */
    public function versions(string $id): array
    {
        Validation::requireId($id, 'id');
        return $this->http->json('GET', '/v1/form-templates/' . rawurlencode($id) . '/versions');
    }

    /**
     * Upload a new version of an existing template. Re-runs the full pipeline (scan, safety checks,
     * field re-enumeration).
     *
     * @param array{data:string,filename:string,contentType?:string|null} $file
     * @return array<string,mixed>
     */
    public function addVersion(string $id, array $file): array
    {
        Validation::requireId($id, 'id');
        return $this->http->multipart(
            'POST',
            '/v1/form-templates/' . rawurlencode($id) . '/versions',
            [],
            ['file' => $this->filePart($file, 'template.pdf')]
        );
    }

    /**
     * Fill and render the template with `payload` (keyed by the AcroForm's dotted field name). Stored
     * as an ordinary document — hash chain, retention, and delivery are all inherited. Returns `202`.
     *
     * @param array<string,mixed> $params payload
     * @return array<string,mixed>
     */
    public function fill(string $id, array $params): array
    {
        Validation::requireId($id, 'id');
        Validation::requireObjectBody($params, 'params');
        Validation::requireObjectBody($params['payload'] ?? null, 'params.payload');
        return $this->http->json('POST', '/v1/form-templates/' . rawurlencode($id) . '/fill', $params);
    }

    /**
     * @param array{data:string,filename?:string,contentType?:string|null} $file
     * @return array{data:string,filename:string,contentType?:string|null}
     */
    private function filePart(array $file, string $fallbackFilename): array
    {
        return [
            'data' => (string) ($file['data'] ?? ''),
            'filename' => (string) ($file['filename'] ?? $fallbackFilename),
            'contentType' => $file['contentType'] ?? null,
        ];
    }
}
