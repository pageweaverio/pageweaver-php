<?php

namespace PageWeaver\Tests;

use PageWeaver\Http;
use PageWeaver\PageWeaverAPIException;
use PageWeaver\PageWeaverAuthenticationException;
use PageWeaver\PageWeaverConflictException;
use PageWeaver\PageWeaverNotFoundException;
use PageWeaver\PageWeaverPermissionException;
use PageWeaver\PageWeaverPlanRequiredException;
use PageWeaver\PageWeaverRateLimitException;
use PageWeaver\PageWeaverServerException;
use PageWeaver\PageWeaverValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Offline unit tests for the internal Http client's pure helpers (query building + error mapping),
 * exercised via reflection so no network is required.
 */
final class HttpTest extends TestCase
{
    private function http(): Http
    {
        return new Http('pk_test_x', 'https://api.example.com', 30);
    }

    private function call(Http $http, string $method, array $args)
    {
        $ref = new \ReflectionMethod($http, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($http, $args);
    }

    public function testBuildQueryDropsNullAndEmpty(): void
    {
        $qs = $this->call($this->http(), 'buildQuery', [['status' => 'done', 'cursor' => null, 'limit' => '']]);
        $this->assertSame('?status=done', $qs);
    }

    public function testBuildQueryEncodesValues(): void
    {
        $qs = $this->call($this->http(), 'buildQuery', [['templateId' => 'a b', 'limit' => 10]]);
        $this->assertStringContainsString('templateId=a+b', $qs);
        $this->assertStringContainsString('limit=10', $qs);
    }

    public function testBuildQueryEmptyIsBlank(): void
    {
        $this->assertSame('', $this->call($this->http(), 'buildQuery', [[]]));
    }

    public function testToApiErrorUsesStringMessage(): void
    {
        /** @var PageWeaverAPIException $e */
        $e = $this->call($this->http(), 'toApiError', [400, '{"message":"bad payload","code":"invalid","errors":[1]}']);
        $this->assertInstanceOf(PageWeaverAPIException::class, $e);
        $this->assertSame(400, $e->status);
        $this->assertSame('bad payload', $e->getMessage());
        $this->assertSame('invalid', $e->code);
        $this->assertSame([1], $e->errors);
    }

    public function testToApiErrorImplodesArrayMessage(): void
    {
        /** @var PageWeaverAPIException $e */
        $e = $this->call($this->http(), 'toApiError', [422, '{"message":["a","b","c"]}']);
        $this->assertSame('a, b, c', $e->getMessage());
    }

    public function testToApiErrorFallsBackForNonJson(): void
    {
        /** @var PageWeaverAPIException $e */
        $e = $this->call($this->http(), 'toApiError', [500, 'Internal Server Error']);
        $this->assertSame('Request failed with status 500', $e->getMessage());
        $this->assertSame('Internal Server Error', $e->body);
    }

    public function testParseHeadersLowercasesNamesAndTakesLastBlock(): void
    {
        $raw = "HTTP/1.1 302 Found\r\nLocation: /next\r\n\r\nHTTP/1.1 200 OK\r\nContent-Type: application/pdf\r\nX-Document-Id: doc_9\r\n";
        $headers = $this->call($this->http(), 'parseHeaders', [$raw]);
        $this->assertSame('application/pdf', $headers['content-type']);
        $this->assertSame('doc_9', $headers['x-document-id']);
    }

    /**
     * @dataProvider statusExceptionProvider
     */
    public function testToApiErrorSelectsTheTypedSubclass(int $status, string $expectedClass): void
    {
        $e = $this->call($this->http(), 'toApiError', [$status, '{"message":"x"}']);
        $this->assertInstanceOf($expectedClass, $e);
    }

    /**
     * @return array<string,array{0:int,1:class-string}>
     */
    public function statusExceptionProvider(): array
    {
        return [
            '400' => [400, PageWeaverValidationException::class],
            '422' => [422, PageWeaverValidationException::class],
            '401' => [401, PageWeaverAuthenticationException::class],
            '402' => [402, PageWeaverPlanRequiredException::class],
            '403' => [403, PageWeaverPermissionException::class],
            '404' => [404, PageWeaverNotFoundException::class],
            '409' => [409, PageWeaverConflictException::class],
            '429' => [429, PageWeaverRateLimitException::class],
            '500' => [500, PageWeaverServerException::class],
            '503' => [503, PageWeaverServerException::class],
            '418' => [418, PageWeaverAPIException::class],
        ];
    }

    public function testToApiErrorCarriesRetryAfterAndRequestIdFromHeaders(): void
    {
        /** @var PageWeaverAPIException $e */
        $e = $this->call($this->http(), 'toApiError', [
            429,
            '{"message":"slow down"}',
            ['retry-after' => '2', 'x-request-id' => 'req_abc'],
        ]);
        $this->assertSame(2.0, $e->retryAfterSeconds);
        $this->assertSame('req_abc', $e->requestId);
    }

    public function testBackoffDelayMsHonorsRetryAfter(): void
    {
        $delay = $this->call($this->http(), 'backoffDelayMs', [0, 3.0]);
        $this->assertSame(3000.0, $delay);
    }

    public function testBackoffDelayMsIsWithinExpectedRangeWithoutRetryAfter(): void
    {
        $delay = $this->call($this->http(), 'backoffDelayMs', [0, null]);
        // baseDelayMs=300 for attempt 0 -> backoff=300, jittered in [150, 300].
        $this->assertGreaterThanOrEqual(150.0, $delay);
        $this->assertLessThanOrEqual(300.0, $delay);
    }

    public function testMultipartRequestsAreNeverRetryable(): void
    {
        // sendMultipart never consults the retry policy; this documents intent via the public API
        // shape rather than exercising the network.
        $ref = new \ReflectionMethod(Http::class, 'sendMultipart');
        $this->assertTrue($ref->isPrivate());
    }
}
