<?php

namespace PageWeaver;

/**
 * Webhook signature verification. PageWeaver signs each delivery with HMAC-SHA256 over the exact
 * request body, keyed by your account webhook secret (`whsec_...`), and sends it in the
 * `X-PageWeaver-Signature` header formatted `sha256=<hex>`. Verify it before trusting the payload.
 */
final class Webhooks
{
    /** Header carrying the `sha256=<hex>` signature. */
    public const SIGNATURE_HEADER = 'x-pageweaver-signature';

    /** Header carrying the event name. */
    public const EVENT_HEADER = 'x-pageweaver-event';

    /** Header carrying the unix-seconds send time. */
    public const TIMESTAMP_HEADER = 'x-pageweaver-timestamp';

    /** Compute the `sha256=<hex>` signature for a body. */
    public static function sign(string $secret, string $body): string
    {
        return 'sha256=' . bin2hex(hash_hmac('sha256', $body, $secret, true));
    }

    /** Constant-time check of a `sha256=<hex>` signature against the raw body. Never throws. */
    public static function verifySignature(string $secret, string $body, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }
        $expected = self::sign($secret, $body);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify a webhook signature and return the parsed, typed event as an associative array. Throws
     * {@link PageWeaverWebhookSignatureException} if the signature is missing or wrong.
     *
     * @return array<string,mixed>
     */
    public static function verifyWebhook(string $secret, string $body, ?string $signature): array
    {
        if (!self::verifySignature($secret, $body, $signature)) {
            throw new PageWeaverWebhookSignatureException();
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
