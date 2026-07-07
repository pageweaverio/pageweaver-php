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
`$pw->comments`, `$pw->reviews`, `$pw->shareLinks`, `$pw->environments`, `$pw->deployments`.
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
```

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
$pw->comments->resolve($thread['id']);

$link = $pw->shareLinks->create(['documentId' => $id, 'capabilities' => ['view', 'comment']]);
echo $link['url']; // shown exactly once
```

## Environments & deployments

```php
$pw->environments->create(['slug' => 'production', 'name' => 'Production']);
$pw->environments->setPin('production', 'tmpl_invoice', 3);
$pw->environments->promote('production', ['fromSlug' => 'staging']);

$plan = $pw->deployments->plan(['manifest' => $manifestYaml, 'files' => $files, 'environment' => 'production']);
$pw->deployments->apply($plan['id']);
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

Every SDK error extends `PageWeaver\PageWeaverException`:

- `PageWeaverApiException` — non-2xx response; carries `->status`, `->code`, `->errors`, `->body`.
- `PageWeaverConnectionException` — transport/DNS/timeout failure before a response.
- `PageWeaverTimeoutException` — `waitFor`/`createAndWait` exceeded its timeout.
- `PageWeaverDocumentFailedException` — the document reached `failed` while waiting; carries `->document`.
- `PageWeaverWebhookSignatureException` — webhook signature mismatch.

## License

MIT
