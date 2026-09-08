<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ManyToManyIdField::class)]
class ManyToManyIdFieldTest extends TestCase
{
    public function testConstructorConfiguresStorageAndAssociationName(): void
    {
        $field = new ManyToManyIdField('stream_ids', 'streamIds', 'streams');

        static::assertSame('stream_ids', $field->getStorageName());
        static::assertSame('streamIds', $field->getPropertyName());
        static::assertSame('streams', $field->getAssociationName());
        static::assertTrue($field->is(WriteProtected::class));
    }
}
