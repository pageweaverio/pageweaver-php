<?php

namespace PageWeaver\Tests;

use PageWeaver\Http;
use PageWeaver\PageWeaverApiException;
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
        /** @var PageWeaverApiException $e */
        $e = $this->call($this->http(), 'toApiError', [400, '{"message":"bad payload","code":"invalid","errors":[1]}']);
        $this->assertInstanceOf(PageWeaverApiException::class, $e);
        $this->assertSame(400, $e->status);
        $this->assertSame('bad payload', $e->getMessage());
        $this->assertSame('invalid', $e->code);
        $this->assertSame([1], $e->errors);
    }

    public function testToApiErrorImplodesArrayMessage(): void
    {
        /** @var PageWeaverApiException $e */
        $e = $this->call($this->http(), 'toApiError', [422, '{"message":["a","b","c"]}']);
        $this->assertSame('a, b, c', $e->getMessage());
    }

    public function testToApiErrorFallsBackForNonJson(): void
    {
        /** @var PageWeaverApiException $e */
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
}
