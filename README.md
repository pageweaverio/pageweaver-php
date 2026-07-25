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

It cannot be combined with an image format, a PDF open-password, a digital signature, or a `url`
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
