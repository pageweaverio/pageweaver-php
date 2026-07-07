<?php

namespace PageWeaver\Tests;

use PageWeaver\Client;
use PageWeaver\PageWeaverApiException;
use PageWeaver\PageWeaverException;
use PageWeaver\Resource\Comments;
use PageWeaver\Resource\Deployments;
use PageWeaver\Resource\Documents;
use PageWeaver\Resource\Environments;
use PageWeaver\Resource\Proposals;
use PageWeaver\Resource\Reviews;
use PageWeaver\Resource\Schemas;
use PageWeaver\Resource\ShareLinks;
use PageWeaver\Resource\Templates;
use PageWeaver\Resource\Usage;
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
        $prop = (new \ReflectionClass($client))->getProperty('http');
        $prop->setAccessible(true);
        $http = $prop->getValue($client);
        $baseUrlProp = (new \ReflectionClass($http))->getProperty('baseUrl');
        $baseUrlProp->setAccessible(true);
        $this->assertSame('https://api.example.com', $baseUrlProp->getValue($http));
    }

    public function testWiresAllNineResources(): void
    {
        $pw = new Client('pk_test_x');
        $this->assertInstanceOf(Documents::class, $pw->documents);
        $this->assertInstanceOf(Templates::class, $pw->templates);
        $this->assertInstanceOf(Schemas::class, $pw->schemas);
        $this->assertInstanceOf(Usage::class, $pw->usage);
        $this->assertInstanceOf(Comments::class, $pw->comments);
        $this->assertInstanceOf(Reviews::class, $pw->reviews);
        $this->assertInstanceOf(ShareLinks::class, $pw->shareLinks);
        $this->assertInstanceOf(Environments::class, $pw->environments);
        $this->assertInstanceOf(Deployments::class, $pw->deployments);
    }

    public function testTemplatesExposesProposals(): void
    {
        $pw = new Client('pk_test_x');
        $this->assertInstanceOf(Proposals::class, $pw->templates->proposals);
    }

    public function testApiExceptionIsAPageWeaverException(): void
    {
        $e = new PageWeaverApiException(402, 'quota exceeded', 'quota', ['pages' => 0], ['message' => 'quota exceeded']);
        $this->assertInstanceOf(PageWeaverException::class, $e);
        $this->assertSame(402, $e->status);
        $this->assertSame('quota exceeded', $e->getMessage());
        $this->assertSame('quota', $e->code);
        $this->assertSame(['pages' => 0], $e->errors);
        $this->assertSame(['message' => 'quota exceeded'], $e->body);
    }
}
