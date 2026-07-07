<?php

namespace PageWeaver\Tests;

use PageWeaver\PageWeaverWebhookSignatureException;
use PageWeaver\Webhooks;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    public function testSignatureRoundTrip(): void
    {
        $secret = 'whsec_test_secret';
        $body = '{"event":"document.completed","documentId":"doc_1"}';
        $sig = Webhooks::sign($secret, $body);

        $this->assertStringStartsWith('sha256=', $sig);
        $this->assertTrue(Webhooks::verifySignature($secret, $body, $sig));
    }

    public function testSignatureFormatIsHmacSha256Hex(): void
    {
        $secret = 'whsec_test_secret';
        $body = 'payload';
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        $this->assertSame($expected, Webhooks::sign($secret, $body));
    }

    public function testWrongSignatureFails(): void
    {
        $this->assertFalse(Webhooks::verifySignature('whsec_x', 'body', 'sha256=deadbeef'));
    }

    public function testMissingSignatureFails(): void
    {
        $this->assertFalse(Webhooks::verifySignature('whsec_x', 'body', null));
        $this->assertFalse(Webhooks::verifySignature('whsec_x', 'body', ''));
    }

    public function testVerifyWebhookReturnsParsedPayload(): void
    {
        $secret = 'whsec_test_secret';
        $body = '{"event":"document.completed","documentId":"doc_1","status":"done"}';
        $sig = Webhooks::sign($secret, $body);

        $payload = Webhooks::verifyWebhook($secret, $body, $sig);
        $this->assertSame('document.completed', $payload['event']);
        $this->assertSame('doc_1', $payload['documentId']);
    }

    public function testVerifyWebhookThrowsOnMismatch(): void
    {
        $this->expectException(PageWeaverWebhookSignatureException::class);
        Webhooks::verifyWebhook('whsec_x', '{}', 'sha256=bad');
    }

    public function testHeaderConstants(): void
    {
        $this->assertSame('x-pageweaver-signature', Webhooks::SIGNATURE_HEADER);
        $this->assertSame('x-pageweaver-event', Webhooks::EVENT_HEADER);
        $this->assertSame('x-pageweaver-timestamp', Webhooks::TIMESTAMP_HEADER);
    }
}
