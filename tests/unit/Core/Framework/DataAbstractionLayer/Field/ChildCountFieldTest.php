<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChildCountField::class)]
class ChildCountFieldTest extends TestCase
{
    public function testConstructorConfiguresStorageAndSystemScopeWriteProtection(): void
    {
        $field = new ChildCountField();

        static::assertSame('child_count', $field->getStorageName());
        static::assertSame('childCount', $field->getPropertyName());

        $flag = $field->getFlag(WriteProtected::class);
        static::assertInstanceOf(WriteProtected::class, $flag);
        static::assertSame([Context::SYSTEM_SCOPE], $flag->getAllowedScopes());
    }
}
