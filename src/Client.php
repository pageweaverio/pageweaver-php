<?php

namespace PageWeaver;

use PageWeaver\Resource\Comments;
use PageWeaver\Resource\Deployments;
use PageWeaver\Resource\Documents;
use PageWeaver\Resource\Environments;
use PageWeaver\Resource\ErrorCodes;
use PageWeaver\Resource\Events;
use PageWeaver\Resource\FormTemplates;
use PageWeaver\Resource\Intake;
use PageWeaver\Resource\ObjectTypes;
use PageWeaver\Resource\Objects;
use PageWeaver\Resource\RelationshipTypes;
use PageWeaver\Resource\Reviews;
use PageWeaver\Resource\Schemas;
use PageWeaver\Resource\Search;
use PageWeaver\Resource\ShareLinks;
use PageWeaver\Resource\Templates;
use PageWeaver\Resource\Usage;
use PageWeaver\Resource\WorkflowDefinitions;

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

    /** Typed business-record type definitions (`objects:read` to read, `object-types:manage` to write). @var ObjectTypes */
    public $objectTypes;

    /** Typed business records (`objects:read`/`objects:write`; `relationships:manage` for edges + document links). @var Objects */
    public $objects;

    /** Relationship-type definitions between object types (`objects:read` to read, `relationships:manage` to write). @var RelationshipTypes */
    public $relationshipTypes;

    /** Full-text search across objects and documents (`search:read`, plus `objects:read` for object hits). @var Search */
    public $search;

    /** Read-only workflow definitions (`workflows:read`). @var WorkflowDefinitions */
    public $workflowDefinitions;

    /** Fillable AcroForm PDF templates: upload, then fill (`documents:upload` to upload, `render` to fill). @var FormTemplates */
    public $formTemplates;

    /** First-class document ingestion — upload a PDF you already have (`documents:upload`). @var Intake */
    public $intake;

    /** The public error-code catalog (`GET /v1/errors`); no API key required. @var ErrorCodes */
    public $errorCodes;

    /** The append-only domain-event ledger (baseline `read` scope). @var Events */
    public $events;

    /** @var Http */
    private $http;

    /**
     * @param array{maxRetries?:int,baseDelayMs?:int,maxDelayMs?:int} $retry Automatic retry policy
     *     for transient failures (429, 5xx, network errors) on requests safe to repeat. Pass
     *     `['maxRetries' => 0]` to disable.
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeout = 30,
        array $retry = []
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        $this->http = new Http($apiKey, rtrim($baseUrl, '/'), $timeout, $retry);

        $this->documents = new Documents($this->http);
        $this->templates = new Templates($this->http);
        $this->schemas = new Schemas($this->http);
        $this->usage = new Usage($this->http);
        $this->comments = new Comments($this->http);
        $this->reviews = new Reviews($this->http);
        $this->shareLinks = new ShareLinks($this->http);
        $this->environments = new Environments($this->http);
        $this->deployments = new Deployments($this->http);
        $this->objectTypes = new ObjectTypes($this->http);
        $this->objects = new Objects($this->http);
        $this->relationshipTypes = new RelationshipTypes($this->http);
        $this->search = new Search($this->http);
        $this->workflowDefinitions = new WorkflowDefinitions($this->http);
        $this->formTemplates = new FormTemplates($this->http);
        $this->intake = new Intake($this->http);
        $this->errorCodes = new ErrorCodes($this->http);
        $this->events = new Events($this->http);
    }
}
