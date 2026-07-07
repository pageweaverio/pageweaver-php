<?php

namespace PageWeaver\Tests;

use PageWeaver\Client;
use PageWeaver\PageWeaverException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testRequiresApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('');
    }

    public function testTrimsBaseUrl(): void
    {
        $client = new Client('pk_test_x', 'https://api.example.com/');
        $prop = (new \ReflectionClass($client))->getProperty('baseUrl');
        $prop->setAccessible(true);
        $this->assertSame('https://api.example.com', $prop->getValue($client));
    }

    public function testExceptionCarriesStatusAndBody(): void
    {
        $e = new PageWeaverException('boom', 402, ['message' => 'quota']);
        $this->assertSame(402, $e->status);
        $this->assertSame(['message' => 'quota'], $e->body);
    }
}
