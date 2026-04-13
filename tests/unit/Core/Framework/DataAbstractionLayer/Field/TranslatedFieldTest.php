<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;

/**
 * @internal
 */
#[CoversClass(TranslatedField::class)]
class TranslatedFieldTest extends TestCase
{
    public function testInstantiate(): void
    {
        $field = new TranslatedField('name');

        static::assertFalse($field->useForSorting());

        $field = new TranslatedField(
            'name',
            true,
        );

        static::assertSame('name', $field->getPropertyName());
        static::assertTrue($field->useForSorting());
    }
}
