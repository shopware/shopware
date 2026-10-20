<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeBreadcrumbField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TreeBreadcrumbField::class)]
class TreeBreadcrumbFieldTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $field = new TreeBreadcrumbField();

        static::assertSame('breadcrumb', $field->getStorageName());
        static::assertSame('breadcrumb', $field->getPropertyName());
        static::assertSame('name', $field->getNameField());

        $flag = $field->getFlag(WriteProtected::class);
        static::assertInstanceOf(WriteProtected::class, $flag);
        static::assertSame([Context::SYSTEM_SCOPE], $flag->getAllowedScopes());
    }

    public function testConstructorPassesExplicitValuesToTheParent(): void
    {
        $field = new TreeBreadcrumbField('auto_breadcrumb', 'autoBreadcrumb', 'title');

        static::assertSame('auto_breadcrumb', $field->getStorageName());
        static::assertSame('autoBreadcrumb', $field->getPropertyName());
        static::assertSame('title', $field->getNameField());
    }
}
