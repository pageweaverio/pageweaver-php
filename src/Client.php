<?php

namespace PageWeaver;

use PageWeaver\Resource\Comments;
use PageWeaver\Resource\Deployments;
use PageWeaver\Resource\Documents;
use PageWeaver\Resource\Environments;
use PageWeaver\Resource\Reviews;
use PageWeaver\Resource\Schemas;
use PageWeaver\Resource\ShareLinks;
use PageWeaver\Resource\Templates;
use PageWeaver\Resource\Usage;

/**
 * The PageWeaver API client. Resources are exposed as public properties.
 *
 * ```php
 * $pw = new \PageWeaver\Client('pk_live_...');
 * $doc = $pw->documents->createAndWait(['templateId' => 'tmpl_invoice', 'payload' => ['total' => 42]]);
 * $pdf = $pw->documents->download($doc['id']);
 * ```
 */
class Client
{
    private const DEFAULT_BASE_URL = 'https://api.pageweaver.io';

    /** @var Documents */
    public $documents;

    /** @var Templates */
    public $templates;

    /** @var Schemas */
    public $schemas;

    /** @var Usage */
    public $usage;

    /** Anchored comment threads on documents (requires a `review`-scoped key for writes). @var Comments */
    public $comments;

    /** Review requests + approvals on documents (requires a `review`-scoped key for writes). @var Reviews */
    public $reviews;

    /** Capability-scoped external share links (requires a `review`-scoped key). @var ShareLinks */
    public $shareLinks;

    /** Named per-account environments + pins (requires a `deploy`-scoped key for writes). @var Environments */
    public $environments;

    /** Plan/apply documents-as-code deployments (requires a `deploy`-scoped key for writes). @var Deployments */
    public $deployments;

    /** @var Http */
    private $http;

    public function __construct(string $apiKey, string $baseUrl = self::DEFAULT_BASE_URL, int $timeout = 30)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        $this->http = new Http($apiKey, rtrim($baseUrl, '/'), $timeout);

        $this->documents = new Documents($this->http);
        $this->templates = new Templates($this->http);
        $this->schemas = new Schemas($this->http);
        $this->usage = new Usage($this->http);
        $this->comments = new Comments($this->http);
        $this->reviews = new Reviews($this->http);
        $this->shareLinks = new ShareLinks($this->http);
        $this->environments = new Environments($this->http);
        $this->deployments = new Deployments($this->http);
    }
}
