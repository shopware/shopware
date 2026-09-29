<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\EventData\FormDataObjectType;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FormDataObjectType::class)]
class FormDataObjectTypeTest extends TestCase
{
    public function testToArrayKeepsObjectTypeShapeAndAddsMarker(): void
    {
        static::assertSame(
            [
                'type' => ObjectType::TYPE,
                'data' => null,
                FormDataObjectType::MARKER => true,
            ],
            (new FormDataObjectType())->toArray()
        );
    }
}
