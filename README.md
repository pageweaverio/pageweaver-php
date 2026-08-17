# PageWeaver PHP SDK

Official PHP client for the [PageWeaver](https://pageweaver.io) PDF generation API. No runtime dependencies (uses the bundled `curl` and `json` extensions). PHP 7.4+.

## Install

```bash
composer require pageweaver/sdk
```

## Quick start

```php
<?php
require 'vendor/autoload.php';

use PageWeaver\Client;

$pw = new Client('pk_live_...');
// Optionally: new Client('pk_live_...', 'https://api.pageweaver.io', 30);

// Create a document and wait for it to finish rendering.
$doc = $pw->documents->createAndWait([
    'templateId' => 'tmpl_invoice',
    'payload' => ['number' => 'INV-001', 'total' => 4200],
]);
echo $doc['status']; // "done"

// Download the finished PDF bytes.
file_put_contents('invoice.pdf', $pw->documents->download($doc['id']));
```

The client exposes a resource per API area:
`$pw->documents`, `$pw->templates` (with `$pw->templates->proposals`), `$pw->schemas`, `$pw->usage`,
`$pw->comments`, `$pw->reviews`, `$pw->shareLinks`, `$pw->environments`, `$pw->deployments`,
`$pw->objectTypes`, `$pw->objects`, `$pw->relationshipTypes`, `$pw->search`, `$pw->workflowDefinitions`,
`$pw->formTemplates`, `$pw->intake` (with `$pw->intake->sessions`), `$pw->errorCodes`, `$pw->events`.
Every method returns an associative array (or a list of them).

## Documents

```php
// Fire and poll yourself.
$created = $pw->documents->create(['html' => '<h1>Hello {{ name }}</h1>', 'payload' => ['name' => 'Ada']]);
$doc = $pw->documents->waitFor($created['id']);

// Idempotent create.
$pw->documents->create($body, 'my-idempotency-key');

// Synchronous create (server holds the response open until the render finishes).
$out = $pw->documents->createSync(['templateId' => 'tmpl_invoice', 'payload' => $payload]);
if ($out['kind'] === 'document') {
    echo $out['document']['download']['url'];
} elseif ($out['kind'] === 'pending') {
    $doc = $pw->documents->waitFor($out['id']); // deadline elapsed, fall back to polling
}

// Or stream raw PDF bytes directly.
$res = $pw->documents->createSync($body, true);
if ($res['kind'] === 'pdf') {
    file_put_contents('out.pdf', $res['pdf']);
}

// History, integrity, replay.
$page = $pw->documents->list(['status' => 'done', 'limit' => 50]);
$all = $pw->documents->listAll(['status' => 'failed']); // follows nextCursor, returns one flat array
$proof = $pw->documents->verify($id);
$again = $pw->documents->regenerate($id);

// Download a protected document.
$pdf = $pw->documents->download($id, 'the-download-password');

// Dry-run a payload against a template's schema for free: nothing is rendered, nothing is metered.
$check = $pw->documents->validate(['templateId' => 'tmpl_invoice', 'payload' => $payload]);

// Name a document and mint a permanent public link. `alias.token` is returned only on this response.
$created = $pw->documents->create([
    'templateId' => 'tmpl_invoice',
    'payload' => $payload,
    'name' => 'March invoice run',
    'publicAlias' => true,
]);
```

### Document lineage: trust, diff, versions, representations

```php
$pw->documents->trust($id);          // one deterministic integrity + provenance manifest
$pw->documents->diff($id, $otherId); // causal diff between two documents; never renders or meters

// Reissue a template-pinned document under the same lineage (fires `document.superseded`):
$pw->documents->appendVersion($id, ['payload' => ['total' => 51]]);
$pw->documents->versions($id);          // the full lineage, newest first
$pw->documents->version($id, 2);        // one immutable pinned version (1-based seq)
$pw->documents->representations($id);   // every artifact of one version (PDF, e-invoice XML, JSON twin, ...)
```

### Archival PDF/A

```php
$doc = $pw->documents->createAndWait([
    'templateId' => 'tmpl_invoice',
    'payload'    => ['number' => 'INV-001'],
    'output'     => ['pdfa' => '3b'],   // "2b" | "3b" | "none"
]);
echo $doc['pdfa'];                       // "3b"
print_r($doc['outputNotices']);          // what had to change to honor the request
```

`2b` and `3b` produce a validated PDF/A. (`1b` is not offered: the conversion cannot produce one that
passes validation.) Send `'none'` to opt out of a template that defaults to archival output.

Three things change, and two are invisible in the produced document:

- **Links stop working.** Every clickable link annotation is dropped.
- **Some text stops being extractable.** Text set with OpenType feature substitution, most commonly
  `font-variant-numeric: tabular-nums`, looks identical but can no longer be selected, searched, or
  copied. A PDF/A document is therefore **not** a machine-readability guarantee.
- **`Author` is not written**, because PDF/A cannot record it conformantly.

A digital signature works alongside it: the signature is applied after the archival conversion and
the result still validates. It cannot be combined with an image format, a PDF open-password, or a `url`
render, and it adds roughly 200ms plus 25ms per page.


### Accessible PDF/UA

```php
$doc = $pw->documents->createAndWait([
    'templateId' => 'tmpl_invoice',
    'payload'    => ['number' => 'INV-001'],
    'output'     => ['pdfUa' => '1'],   // "1" | "none"
]);
print_r($doc['accessibility']);          // ["standard" => "PDF/UA-1", "conformant" => true, ...]

$report = $pw->documents->accessibility($doc['id']);   // every rule, with its ISO clause
```

`"1"` is the only level (PDF/UA-2 needs PDF 2.0, which the renderer does not emit). Send `"none"` to
opt out of a template that defaults to accessible output.

**Conformance depends on your markup, not only on asking for it.** Your template must set a language
on `<html>`, have a title, give every image real alt text (an empty `alt` is not accepted, use a CSS
background for decoration), label inline SVG with `role="img"` + `aria-label`, keep headings in order
starting at `<h1>`, and use header cells in tables. The mechanical parts are handled for you: the role
map, link descriptions, the document language, marking running headers and footers as artifacts, and
the conformance declaration.

A non-conformant document is a **failed** document by default, so anything you receive with the claim
has been checked by the veraPDF reference validator. Use `"conformance": "attempt"` while adjusting a
template to get the document anyway with the violations listed. A large-print variant is the same
template and payload with `options.page.scale`, validated the same way.

Works alongside a digital signature: the conformance check runs on the signed document, so the
verdict covers the file you receive. Cannot be combined with a watermark, a PDF open-password,
PDF/A, an image format, or a `url` render.

### Localization

```php
$pw->documents->createAndWait([
    'templateId' => 'tmpl_invoice',
    'payload'    => ['total' => 42],
    'options'    => [
        'localization' => [
            'locale' => 'ar-EG',
            'currency' => 'EGP',
            'direction' => 'auto', // "auto" (default, follows the locale) | "ltr" | "rtl"
        ],
    ],
]);
```

`direction` defaults to `"auto"`: an Arabic or Hebrew `locale` alone produces a right-to-left
document with nothing else set. Pass `"ltr"`/`"rtl"` only to override what the locale implies.

## Templates, schemas, usage

```php
$templates = $pw->templates->list();
$template = $pw->templates->get('tmpl_invoice');
$versions = $pw->templates->versions('tmpl_invoice');
$version = $pw->templates->version('tmpl_invoice', 3, 'source');

$schemas = $pw->schemas->list();
$schema = $pw->schemas->get('sch_invoice', 2);

$usage = $pw->usage->get();
```

## Proposals (documents-as-code)

```php
$proposal = $pw->templates->proposals->open('tmpl_invoice', ['fromDraft' => true]);
$pw->templates->proposals->approve('tmpl_invoice', $proposal['id']);
$pw->templates->proposals->promote('tmpl_invoice', $proposal['id']);
```

## Reviews, comments, share links

```php
$review = $pw->reviews->create(['documentId' => $id]);
$pw->reviews->addParticipant($review['id'], ['externalEmail' => 'client@acme.test', 'role' => 'approver']);
$pw->reviews->approve($review['id'], []);

$thread = $pw->comments->create(['documentId' => $id, 'anchor' => [...], 'body' => 'Fix the total']);
$pw->comments->reply($thread['id'], ['body' => 'Done']);
// Nest a reply under a specific prior message instead of flat-appending:
$pw->comments->reply($thread['id'], ['body' => 'Agreed', 'parentMessageId' => $firstMessageId]);
$pw->comments->resolve($thread['id']);

$link = $pw->shareLinks->create(['documentId' => $id, 'capabilities' => ['view', 'comment']]);
echo $link['url']; // shown exactly once
```

## Environments & deployments

```php
$pw->environments->create(['slug' => 'production', 'name' => 'Production']);
$pw->environments->setPin('production', 'tmpl_invoice', 3);
$pw->environments->promote('production', ['fromSlug' => 'staging']);

$plan = $pw->deployments->plan([
    'manifest' => $manifestYaml,
    'files' => $files,
    'environment' => 'production',
    'gitRepo' => 'acme/pageweaver-manifest', // optional: provenance only, "owner/name"
]);
$pw->deployments->apply($plan['id']);
```

## Typed business records (objects, object types, relationships)

Requires an API key with the matching scope: `objects:read` / `objects:write` /
`object-types:manage` / `relationships:manage` (see [Scopes](#scopes)).

```php
// Define a record type, then publish it (freezes an immutable version):
$type = $pw->objectTypes->create([
    'key' => 'invoice',
    'nameSingular' => 'Invoice',
    'namePlural' => 'Invoices',
    'schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'number']]],
]);
$pw->objectTypes->publish($type['id']);

// Create + replace a record. `replace` requires expectedVersion — a 409 on mismatch, never a lost update.
$invoice = $pw->objects->create(['objectTypeKey' => 'invoice', 'data' => ['total' => 42]]);
$pw->objects->replace($invoice['id'], ['data' => ['total' => 51], 'expectedVersion' => $invoice['version']]);

// Relate records, and file a rendered document against one:
$pw->objects->addRelationship($invoice['id'], ['relationshipTypeKey' => 'billed_to', 'targetObjectId' => $customerId]);
$pw->objects->linkDocument($invoice['id'], ['documentId' => $docId, 'role' => 'primary']);
```

## Search, domain events, and the error registry

```php
$pw->search->query(['q' => 'acme invoice', 'subjectType' => 'object']); // requires search:read

// The append-only event ledger — resume from `nextCursor`, not the last event you saw:
foreach ($pw->events->listAll(['type' => 'document.completed']) as $event) {
    echo $event['type'] . ' ' . $event['subjectId'] . PHP_EOL;
}

$pw->errorCodes->list(); // the full public error-code catalog (no API key required)
```

## Document ingestion and fillable PDFs

```php
// Bring in a PDF you already have (not a template render):
$pw->intake->create([
    'file' => ['data' => $bytes, 'filename' => 'scan.pdf'],
    'classification' => 'internal',
]);

// Large files: a resumable chunked session.
$session = $pw->intake->sessions->create(['filename' => 'big.pdf', 'totalBytes' => $totalBytes, 'chunkSize' => $chunkSize]);
$pw->intake->sessions->uploadChunk($session['id'], 0, $chunk0);
$pw->intake->sessions->finalize($session['id']);

// Fill an uploaded PDF's own AcroForm fields (not a Liquid template):
$template = $pw->formTemplates->create(['name' => 'Claim form', 'file' => ['data' => $bytes, 'filename' => 'claim.pdf']]);
$pw->formTemplates->fill($template['id'], ['payload' => ['claimant.fullName' => 'Ada Lovelace']]);
```

## Scopes

Every API key carries the baseline `read` + `render` scopes. Everything else is opt-in, set per key
in the portal:

| Scope | Gates |
| --- | --- |
| `review` | Comments, reviews, share links |
| `deploy` | Environments, deployments, template proposals |
| `objects:read` / `objects:write` | Reading / writing typed business records |
| `objects:read-sensitive` | Decrypting a record's sensitive fields (stacks on `objects:read`) |
| `object-types:manage` | Defining and publishing object types |
| `relationships:manage` | Object relationships, and filing documents against objects |
| `documents:upload` | Document intake and fillable-form-template uploads |
| `search:read` | `$pw->search->query()` |
| `workflows:read` | `$pw->workflowDefinitions->*` |

A call missing a required scope fails with a `403` — a `PageWeaverPermissionException` (see below).

## Retries

GET/HEAD/PUT/DELETE requests, and any POST sent with an idempotency key, are retried automatically
on `429` and `5xx` with exponential backoff + jitter (honoring `Retry-After` on `429`). A plain POST
with no idempotency key is never retried, since a duplicate render or record is worse than a failed
request. Multipart uploads are never retried (the file stream can't be safely replayed). Tune it per
client:

```php
$pw = new \PageWeaver\Client('pk_live_...', 'https://api.pageweaver.io', 30, [
    'maxRetries' => 3,
    'baseDelayMs' => 500,
    'maxDelayMs' => 8000,
]);

// Disable retries entirely:
$pw = new \PageWeaver\Client('pk_live_...', 'https://api.pageweaver.io', 30, ['maxRetries' => 0]);
```

## Webhooks

Verify inbound webhook deliveries before trusting them:

```php
use PageWeaver\Webhooks;

$signature = $_SERVER['HTTP_X_PAGEWEAVER_SIGNATURE'] ?? null;
$body = file_get_contents('php://input'); // the exact raw body

if (Webhooks::verifySignature($secret, $body, $signature)) {
    $event = json_decode($body, true);
}

// Or verify + parse in one step (throws PageWeaverWebhookSignatureException on mismatch):
$event = Webhooks::verifyWebhook($secret, $body, $signature);
```

## Errors

Every SDK error extends `PageWeaver\PageWeaverException`. A non-2xx API response throws a
`PageWeaverAPIException` subclass selected by status, so you can catch the specific failure kind —
or just `PageWeaverAPIException` to catch all of them:

| Class | Status | Thrown when |
| --- | --- | --- |
| `PageWeaverValidationException` | 400 / 422 | The request body or query failed validation. `->errors` carries field-level detail. |
| `PageWeaverAuthenticationException` | 401 | The API key is missing, invalid, or the account is suspended. |
| `PageWeaverPlanRequiredException` | 402 | A billing problem, not a credential one: the account's plan doesn't include this capability at all — no key, however scoped, can call it until the account upgrades. |
| `PageWeaverPermissionException` | 403 | A credential problem: the key authenticated fine but isn't allowed to do this. Check `->isScopeMissing()` / `->getRequiredScope()` when it's a missing scope. |
| `PageWeaverNotFoundException` | 404 | No such resource (or it belongs to another account). |
| `PageWeaverConflictException` | 409 | An `expectedVersion`/`If-Match` mismatch, a duplicate key, or a state conflict. |
| `PageWeaverRateLimitException` | 429 | Rate limited or over a usage quota. `->retryAfterSeconds` when the API sent `Retry-After`. |
| `PageWeaverServerException` | 5xx | The API failed unexpectedly. |
| `PageWeaverAPIException` | any | The base class — every subclass above extends it, and it also covers any other status. |
| `PageWeaverInvalidRequestException` | — | A client-side shape check failed before any request was sent (e.g. a blank id). |
| `PageWeaverConnectionException` | — | A network failure, or the request timed out. |
| `PageWeaverTimeoutException` | — | `waitFor`/`createAndWait` exceeded its timeout before the document finished. |
| `PageWeaverDocumentFailedException` | — | The document reached the `failed` state while waiting. Carries `->document`. |
| `PageWeaverWebhookSignatureException` | — | A webhook signature did not match the body. |

```php
use PageWeaver\PageWeaverValidationException;
use PageWeaver\PageWeaverRateLimitException;
use PageWeaver\PageWeaverPlanRequiredException;
use PageWeaver\PageWeaverPermissionException;
use PageWeaver\PageWeaverAPIException;

try {
    $pw->documents->create(['templateId' => 't', 'payload' => $payload, 'output' => ['format' => 'facturx']]);
} catch (PageWeaverValidationException $e) {
    echo 'Validation failed: ' . print_r($e->errors, true);
} catch (PageWeaverRateLimitException $e) {
    echo 'Rate limited, retry after ' . $e->retryAfterSeconds . ' seconds';
} catch (PageWeaverPlanRequiredException $e) {
    // A billing problem: the account's plan doesn't include this feature at all.
    echo 'Upgrade required: ' . $e->getMessage();
} catch (PageWeaverPermissionException $e) {
    // A credential problem: this specific API key isn't allowed to do this.
    if ($e->isScopeMissing()) {
        echo "Mint a key with the '{$e->getRequiredScope()}' scope.";
    } else {
        echo 'Forbidden: ' . $e->getMessage();
    }
} catch (PageWeaverAPIException $e) {
    echo $e->code . ' ' . $e->status . ' ' . $e->requestId;
}
```

Look up any `$e->code` in `$pw->errorCodes->list()` for its cause and resolution.
`PageWeaverPlanRequiredException` (402) and `PageWeaverPermissionException` (403) are easy to
conflate — both read as "you can't do that" — but the fix differs: a plan error is resolved by the
account upgrading, a scope error by minting a new API key with the missing scope. Catch the
specific class, not the status code.

## Migrating off living documents

The `/v1/living-documents/*` surface has been retired and folded into ordinary documents:

- `livingDocuments->create(['templateId' => ..., 'payload' => ..., 'publicAlias' => true])` →
  `documents->create(['templateId' => ..., 'payload' => ..., 'publicAlias' => true])`. The minted
  link comes back as `$result['alias']['token']` instead of a separate identity.
- `livingDocuments->reissue($id, ['payload' => ...])` → `documents->appendVersion($documentId, ['payload' => ...])`.
- `livingDocuments->get($id)` / `->list()` / `->version($id, $seq)` →
  `documents->versions($documentId)` / `documents->version($documentId, $seq)`.

## License

MIT
