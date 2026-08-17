<?php

namespace PageWeaver\Tests;

use PageWeaver\PageWeaverInvalidRequestException;
use PageWeaver\Validation;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testRequireIdAcceptsNonEmptyString(): void
    {
        $this->assertSame('doc_1', Validation::requireId('doc_1', 'id'));
    }

    public function testRequireIdRejectsBlank(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireId('   ', 'id');
    }

    public function testRequireIdRejectsNull(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireId(null, 'id');
    }

    public function testRequirePositiveIntRejectsZeroAndNegative(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requirePositiveInt(0, 'version');
    }

    public function testRequirePositiveIntAcceptsPositive(): void
    {
        $this->assertSame(3, Validation::requirePositiveInt(3, 'version'));
    }

    public function testRequireNonNegativeIntAcceptsZero(): void
    {
        $this->assertSame(0, Validation::requireNonNegativeInt(0, 'index'));
    }

    public function testRequireNonNegativeIntRejectsNegative(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireNonNegativeInt(-1, 'index');
    }

    public function testRequireObjectBodyRejectsList(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireObjectBody([1, 2, 3], 'params');
    }

    public function testRequireObjectBodyAcceptsAssociativeArray(): void
    {
        $this->assertSame(['a' => 1], Validation::requireObjectBody(['a' => 1], 'params'));
    }

    public function testRequireStringRejectsBlank(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireString('', 'name');
    }

    public function testRequireNonEmptyArrayRejectsEmpty(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireNonEmptyArray([], 'files');
    }

    public function testRequireOneOfRejectsBothSet(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireOneOf('a', 'a', 'b', 'b');
    }

    public function testRequireOneOfRejectsNeitherSet(): void
    {
        $this->expectException(PageWeaverInvalidRequestException::class);
        Validation::requireOneOf(null, 'a', null, 'b');
    }

    public function testRequireOneOfAcceptsExactlyOne(): void
    {
        Validation::requireOneOf('a', 'a', null, 'b');
        $this->addToAssertionCount(1);
    }
}
