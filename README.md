# PageWeaver PHP SDK

Official PHP client for the [PageWeaver](https://pageweaver.io) PDF generation API. No runtime dependencies (uses the bundled `curl` and `json` extensions). PHP 7.4+.

## Install

```bash
composer require pageweaver/sdk
```

## Usage

```php
<?php
require 'vendor/autoload.php';

use PageWeaver\Client;

$pw = new Client('pk_live_...');

// Create a document and wait for it to finish rendering
$doc = $pw->createAndWait([
    'templateId' => 'tmpl_invoice',
    'payload' => ['number' => 'INV-001', 'total' => 4200],
]);
echo $doc['status']; // "done"

// Or fire-and-poll yourself
$created = $pw->createDocument(['html' => '<h1>Hello {{ name }}</h1>', 'payload' => ['name' => 'Ada']]);
$result = $pw->getDocument($created['id']);
```

Non-2xx responses throw `PageWeaver\PageWeaverException` (with `->status` and `->body`).

## License

MIT
