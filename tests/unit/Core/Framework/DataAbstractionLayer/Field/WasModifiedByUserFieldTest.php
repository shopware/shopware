<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WasModifiedByUserField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WasModifiedByUserField::class)]
class WasModifiedByUserFieldTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $field = new WasModifiedByUserField();

        static::assertSame('was_modified_by_user', $field->getStorageName());
        static::assertSame('wasModifiedByUser', $field->getPropertyName());
    }

    public function testConstructorPassesExplicitNamesToTheParent(): void
    {
        $field = new WasModifiedByUserField('changed_by_user', 'changedByUser');

        static::assertSame('changed_by_user', $field->getStorageName());
        static::assertSame('changedByUser', $field->getPropertyName());
    }
}
