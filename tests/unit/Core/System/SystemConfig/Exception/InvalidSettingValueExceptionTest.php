<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason: the tested class is removed with the next major - to be removed
 */
#[Package('framework')]
#[CoversClass(InvalidSettingValueException::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class InvalidSettingValueExceptionTest extends TestCase
{
    #[TestDox('the message grows with the optional needed and actual types')]
    public function testMessageVariants(): void
    {
        static::assertSame(
            'Invalid value for \'core.foo\'',
            (new InvalidSettingValueException('core.foo'))->getMessage()
        );
        static::assertSame(
            'Invalid value for \'core.foo\'. Must be of type \'int\'',
            (new InvalidSettingValueException('core.foo', 'int'))->getMessage()
        );
        static::assertSame(
            'Invalid value for \'core.foo\'. Must be of type \'int\'. But is of type \'string\'',
            (new InvalidSettingValueException('core.foo', 'int', 'string'))->getMessage()
        );
    }
}
