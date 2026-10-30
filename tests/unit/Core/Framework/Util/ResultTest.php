<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Result;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Result::class)]
class ResultTest extends TestCase
{
    public function testOkCarriesNoErrors(): void
    {
        $result = Result::ok();

        static::assertTrue($result->isOk());
        static::assertNull($result->errors);
    }

    public function testFailedCarriesThePayload(): void
    {
        $result = Result::failed(['first', 'second']);

        static::assertFalse($result->isOk());
        static::assertSame(['first', 'second'], $result->errors);
    }

    public function testFailedWithAnEmptyPayloadIsStillAFailure(): void
    {
        $result = Result::failed([]);

        static::assertFalse($result->isOk());
        static::assertSame([], $result->errors);
    }

    public function testPayloadIsNotRestrictedToLists(): void
    {
        $errors = new \stdClass();

        $result = Result::failed($errors);

        static::assertFalse($result->isOk());
        static::assertSame($errors, $result->errors);
    }
}
