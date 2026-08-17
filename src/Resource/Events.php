<?php

namespace PageWeaver\Resource;

use PageWeaver\Http;

/**
 * The append-only domain-event ledger: what happened, in order, for correlation and replay.
 * Entries are filtered to what the calling key's scopes can see (a key without `objects:read` sees
 * nothing about object-model events); hidden entries are silently dropped, not an error. Requires
 * only the baseline `read` scope every key has.
 */
class Events
{
    /** @var Http */
    private $http;

    public function __construct(Http $http)
    {
        $this->http = $http;
    }

    /**
     * Page through events. `after` is the resume point (exclusive) — always resume from the returned
     * `nextCursor`, even if it doesn't equal the last event you saw (some may have been scope-trimmed).
     *
     * @param array<string,mixed> $params after, limit, type, subjectType, subjectId, correlationId
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->json('GET', '/v1/events', null, [
            'after' => $params['after'] ?? null,
            'limit' => $params['limit'] ?? null,
            'type' => $params['type'] ?? null,
            'subjectType' => $params['subjectType'] ?? null,
            'subjectId' => $params['subjectId'] ?? null,
            'correlationId' => $params['correlationId'] ?? null,
        ]);
    }

    /**
     * Iterate every visible event forward from `after` (or the beginning), transparently following
     * `nextCursor`. Stops once a page comes back with no events (you have caught up); call again
     * later with the last `after` you saw to resume.
     *
     * ```php
     * foreach ($pw->events->listAll(['type' => 'document.completed']) as $event) {
     *     $after = $event['seq'];
     *     // ...
     * }
     * ```
     *
     * @param array<string,mixed> $params after, limit, type, subjectType, subjectId, correlationId
     * @return \Generator<int,array<string,mixed>>
     */
    public function listAll(array $params = []): \Generator
    {
        $after = $params['after'] ?? null;
        for (;;) {
            $page = $this->list(array_merge($params, ['after' => $after]));
            $events = $page['events'] ?? [];
            if (!is_array($events) || count($events) === 0) {
                return;
            }
            foreach ($events as $event) {
                yield $event;
            }
            $after = $page['nextCursor'] ?? null;
            if ($after === null || $after === '') {
                return;
            }
        }
    }
}
