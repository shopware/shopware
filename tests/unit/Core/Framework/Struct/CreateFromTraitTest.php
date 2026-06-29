<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Struct\CreateFromTrait;
use Shopware\Core\Framework\Struct\Exception\AssignException;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(CreateFromTrait::class)]
class CreateFromTraitTest extends TestCase
{
    public function testCreateFromWithoutAssignMethod(): void
    {
        $fromStruct = new FromStruct();

        $toStruct = ToStruct::createFrom($fromStruct);

        static::assertSame('testFrom', $toStruct->test);
    }

    public function testCreateFromWithoutAssignMethodThrowsException(): void
    {
        $fromStruct = new FromStructWithException();

        $this->expectExceptionObject(AssignException::assignTypeError(new \TypeError('Cannot assign int to property Shopware\Tests\Unit\Core\Framework\Struct\ToStruct::$test of type string')));
        ToStruct::createFrom($fromStruct);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCreateFromWithoutAssignMethodThrowsExceptionDeprecated(): void
    {
        $fromStruct = new FromStructWithException();

        $toStruct = ToStruct::createFrom($fromStruct);

        static::assertSame('testTo', $toStruct->test);
    }
}

/**
 * @internal
 */
class FromStruct extends Struct
{
    public string $test = 'testFrom';
}

/**
 * @internal
 */
class FromStructWithException extends Struct
{
    public int $test = 4;
}

/**
 * @internal
 */
class ToStruct
{
    use CreateFromTrait;

    public string $test = 'testTo';
}
