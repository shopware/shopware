<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TreePathField::class)]
class TreePathFieldTest extends TestCase
{
    public function testConstructorDefaultsThePathFieldToId(): void
    {
        $field = new TreePathField('path', 'path');

        static::assertSame('path', $field->getStorageName());
        static::assertSame('path', $field->getPropertyName());
        static::assertSame('id', $field->getPathField());

        $flag = $field->getFlag(WriteProtected::class);
        static::assertInstanceOf(WriteProtected::class, $flag);
        static::assertSame([Context::SYSTEM_SCOPE], $flag->getAllowedScopes());
    }

    public function testConstructorPassesAnExplicitPathField(): void
    {
        $field = new TreePathField('path', 'path', 'slug');

        static::assertSame('slug', $field->getPathField());
    }
}
