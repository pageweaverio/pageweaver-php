<?php

namespace PageWeaver\Tests;

use PageWeaver\PageWeaverAPIException;
use PageWeaver\PageWeaverAuthenticationException;
use PageWeaver\PageWeaverConflictException;
use PageWeaver\PageWeaverInvalidRequestException;
use PageWeaver\PageWeaverNotFoundException;
use PageWeaver\PageWeaverPermissionException;
use PageWeaver\PageWeaverPlanRequiredException;
use PageWeaver\PageWeaverRateLimitException;
use PageWeaver\PageWeaverServerException;
use PageWeaver\PageWeaverValidationException;
use PHPUnit\Framework\TestCase;

final class ErrorsTest extends TestCase
{
    public function testEverySubclassIsAPageWeaverAPIException(): void
    {
        $classes = [
            PageWeaverValidationException::class,
            PageWeaverAuthenticationException::class,
            PageWeaverPlanRequiredException::class,
            PageWeaverPermissionException::class,
            PageWeaverNotFoundException::class,
            PageWeaverConflictException::class,
            PageWeaverRateLimitException::class,
            PageWeaverServerException::class,
        ];
        foreach ($classes as $class) {
            $e = new $class(400, 'x');
            $this->assertInstanceOf(PageWeaverAPIException::class, $e, $class);
        }
    }

    public function testIsRetryableFor429And5xx(): void
    {
        $this->assertTrue((new PageWeaverRateLimitException(429, 'x'))->isRetryable());
        $this->assertTrue((new PageWeaverServerException(500, 'x'))->isRetryable());
        $this->assertFalse((new PageWeaverValidationException(400, 'x'))->isRetryable());
    }

    public function testPermissionExceptionScopeMissingDetection(): void
    {
        $e = new PageWeaverPermissionException(
            403,
            "Forbidden: missing the 'review' scope",
            'authorization.scope_missing'
        );
        $this->assertTrue($e->isScopeMissing());
        $this->assertSame('review', $e->getRequiredScope());
    }

    public function testPermissionExceptionNotScopeMissing(): void
    {
        $e = new PageWeaverPermissionException(403, 'Forbidden', 'some.other_code');
        $this->assertFalse($e->isScopeMissing());
        $this->assertNull($e->getRequiredScope());
    }

    public function testPermissionExceptionScopeMissingWithoutParsableMessage(): void
    {
        $e = new PageWeaverPermissionException(403, 'Forbidden', 'authorization.scope_missing');
        $this->assertTrue($e->isScopeMissing());
        $this->assertNull($e->getRequiredScope());
    }

    public function testCarriesRetryAfterAndRequestId(): void
    {
        $e = new PageWeaverRateLimitException(429, 'Too many requests', null, null, null, 2.5, 'req_123');
        $this->assertSame(2.5, $e->retryAfterSeconds);
        $this->assertSame('req_123', $e->requestId);
    }

    public function testInvalidRequestExceptionCarriesPath(): void
    {
        $e = new PageWeaverInvalidRequestException('`id` is required.', 'id');
        $this->assertSame('id', $e->path);
        $this->assertSame('`id` is required.', $e->getMessage());
    }
}
