<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;
use PageWeaver\PageWeaverDocumentFailedException;
use PageWeaver\PageWeaverTimeoutException;

/** Operations on documents: the core of the API. */
class Documents
{
    /** @var Http */
    private $http;

    /** Statuses at which a document stops changing. */
    private const TERMINAL = ['done', 'failed'];

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Create a document from a template (with a validated payload), inline HTML, or a URL. Returns
     * `202` immediately with the document id and status. Poll {@link get}, call {@link waitFor}, or use
     * {@link createAndWait} to block until it is ready.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey !== null ? ['idempotency-key' => $idempotencyKey] : [];
        return $this->http->json('POST', '/v1/documents', $body, [], $headers);
    }

    /**
     * Fetch the current state of a document. When `status` is "done" it carries a `download` block.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->json('GET', '/v1/documents/' . rawurlencode($id));
    }

    /**
     * Fetch a document's integrity proof: the SHA-256 `contentHash`, its hash-chain position, and
     * `chainVerified`.
     *
     * @return array<string,mixed>
     */
    public function verify(string $id): array
    {
        return $this->http->json('GET', '/v1/documents/' . rawurlencode($id) . '/verify');
    }

    /**
     * One page of the document history, newest first. Use `nextCursor` to page.
     *
     * @param array<string,mixed> $params status, templateId, cursor, limit
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/documents', null, [
            'status' => $params['status'] ?? null,
            'templateId' => $params['templateId'] ?? null,
            'cursor' => $params['cursor'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Fetch every document across all pages, transparently following `nextCursor`, and return them as
     * one flat list.
     *
     * @param array<string,mixed> $params status, templateId, limit (cursor is ignored/managed)
     * @return array<int,mixed>
     */
    public function listAll(array $params = []): array
    {
        $items = [];
        $cursor = null;
        do {
            $page = $this->list(array_merge($params, ['cursor' => $cursor]));
            $pageItems = $page['items'] ?? [];
            if (is_array($pageItems)) {
                foreach ($pageItems as $item) {
                    $items[] = $item;
                }
            }
            $cursor = $page['nextCursor'] ?? null;
        } while (is_string($cursor) && $cursor !== '');
        return $items;
    }

    /**
     * Faithfully replay a prior document (same version/source, payload, options, download protection).
     * Returns a new document id (`202`); counts as a new render.
     *
     * @return array<string,mixed>
     */
    public function regenerate(string $id): array
    {
        return $this->http->json('POST', '/v1/documents/' . rawurlencode($id) . '/regenerate');
    }

    /**
     * Poll a document until it reaches a terminal state (or the timeout elapses). Resolves with the
     * finished document. By default it throws {@link PageWeaverDocumentFailedException} on failure; pass
     * `['throwOnFailure' => false]` to receive the failed document instead.
     *
     * Options: intervalMs (default 1000), maxIntervalMs (5000), backoff (1.5), timeoutMs (60000),
     * throwOnFailure (true).
     *
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function waitFor(string $id, array $opts = []): array
    {
        $intervalMs = isset($opts['intervalMs']) ? (float) $opts['intervalMs'] : 1000.0;
        $maxIntervalMs = isset($opts['maxIntervalMs']) ? (float) $opts['maxIntervalMs'] : 5000.0;
        $backoff = isset($opts['backoff']) ? (float) $opts['backoff'] : 1.5;
        $timeoutMs = isset($opts['timeoutMs']) ? (float) $opts['timeoutMs'] : 60000.0;
        $throwOnFailure = $opts['throwOnFailure'] ?? true;

        $deadline = microtime(true) + $timeoutMs / 1000.0;
        $delay = $intervalMs;
        $last = $this->get($id);

        while (!$this->isTerminal($last)) {
            if (microtime(true) >= $deadline) {
                throw new PageWeaverTimeoutException(
                    $id,
                    isset($last['status']) && is_string($last['status']) ? $last['status'] : null,
                    $timeoutMs
                );
            }
            $remaining = ($deadline - microtime(true)) * 1000.0;
            $sleepMs = min($delay, $remaining);
            if ($sleepMs > 0) {
                usleep((int) ($sleepMs * 1000));
            }
            $delay = min($delay * $backoff, $maxIntervalMs);
            $last = $this->get($id);
        }

        if (($last['status'] ?? null) === 'failed' && $throwOnFailure) {
            throw new PageWeaverDocumentFailedException($last);
        }
        return $last;
    }

    /**
     * Convenience: {@link create} then {@link waitFor}. Resolves with the finished document.
     *
     * @param array<string,mixed> $body
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function createAndWait(array $body, array $opts = []): array
    {
        $created = $this->create($body, isset($opts['idempotencyKey']) ? (string) $opts['idempotencyKey'] : null);
        $id = $created['id'] ?? null;
        if (!is_string($id) || $id === '') {
            return $created;
        }
        return $this->waitFor($id, $opts);
    }

    /**
     * Create a document synchronously: send `Prefer: wait` so the server holds the response open until
     * the render finishes. Content-negotiated: returns one of
     *   ['kind' => 'pdf', 'id' => ..., 'version' => ..., 'pdf' => <raw bytes string>]
     *   ['kind' => 'pending', 'id' => ..., 'version' => ..., 'status' => ...]
     *   ['kind' => 'document', 'document' => <array>]
     * Pass `$pdf = true` to stream raw PDF bytes (protected/failed documents still come back as JSON).
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function createSync(array $body, bool $pdf = false): array
    {
        $headers = ['prefer' => 'wait'];
        if (isset($body['idempotencyKey'])) {
            $headers['idempotency-key'] = (string) $body['idempotencyKey'];
            unset($body['idempotencyKey']);
        }
        if ($pdf) {
            $headers['accept'] = 'application/pdf';
        }

        $res = $this->http->raw('POST', '/v1/documents', $body, $headers);
        $contentType = $res['headers']['content-type'] ?? '';

        if (stripos($contentType, 'application/pdf') !== false) {
            return [
                'kind' => 'pdf',
                'id' => $res['headers']['x-document-id'] ?? null,
                'version' => $this->numberOrNull($res['headers']['x-document-version'] ?? null),
                'pdf' => $res['body'],
            ];
        }

        $text = $res['body'];
        $decoded = $text === '' ? [] : json_decode($text, true);
        $doc = is_array($decoded) ? $decoded : [];

        if ($res['status'] === 202) {
            return [
                'kind' => 'pending',
                'id' => $doc['id'] ?? null,
                'version' => $doc['version'] ?? null,
                'status' => $doc['status'] ?? null,
            ];
        }
        return ['kind' => 'document', 'document' => $doc];
    }

    /**
     * A document's per-page geometry plus whether extracted text and a thumbnail exist.
     *
     * @return array<int,mixed>
     */
    public function pages(string $id): array
    {
        return $this->http->json('GET', '/v1/documents/' . rawurlencode($id) . '/pages');
    }

    /**
     * Carry open comment threads forward from a previous same-template document onto this one. Returns
     * `202`; observe progress via {@link commentMigration}.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function migrateComments(string $id, array $body): array
    {
        return $this->http->json('POST', '/v1/documents/' . rawurlencode($id) . '/migrate-comments', $body);
    }

    /**
     * The comment-migration rollup for a document, grouped by migration status.
     *
     * @return array<string,mixed>
     */
    public function commentMigration(string $id): array
    {
        return $this->http->json('GET', '/v1/documents/' . rawurlencode($id) . '/comment-migration');
    }

    /**
     * Download the finished PDF bytes. For a download-protected document, pass `$password`. For an
     * unprotected document, the short-lived signed URL is resolved and fetched automatically.
     *
     * @return string raw PDF bytes
     */
    public function download(string $id, ?string $password = null): string
    {
        if ($password !== null) {
            return $this->http->bytes(
                'GET',
                '/v1/documents/' . rawurlencode($id) . '/content',
                ['x-document-password' => $password],
                true
            );
        }

        $doc = $this->get($id);
        $download = $doc['download'] ?? null;
        $url = is_array($download) ? ($download['url'] ?? null) : null;
        if (($doc['status'] ?? null) !== 'done' || !is_string($url) || $url === '') {
            throw new PageWeaverDocumentFailedException($doc);
        }
        if (is_array($download) && !empty($download['protected'])) {
            $doc['error'] = 'Document is download-protected; supply a `password` to download it.';
            throw new PageWeaverDocumentFailedException($doc);
        }
        return $this->http->fetchUrlBytes($url);
    }

    /**
     * @param array<string,mixed> $doc
     */
    private function isTerminal(array $doc): bool
    {
        $status = $doc['status'] ?? null;
        return is_string($status) && in_array($status, self::TERMINAL, true);
    }

    /**
     * @param string|null $value
     * @return int|null
     */
    private function numberOrNull($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }
}
