<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Definition\AgenticDiscoverySalesChannelConfigDefinition;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Extension\AgenticDiscoverySalesChannelExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoverySalesChannelExtension::class)]
class AgenticDiscoverySalesChannelExtensionTest extends TestCase
{
    public function testExtendsSalesChannelEntity(): void
    {
        $extension = new AgenticDiscoverySalesChannelExtension();

        static::assertSame(SalesChannelDefinition::ENTITY_NAME, $extension->getEntityName());
    }

    public function testAddsReverseOneToOneAssociationWithCascadeDelete(): void
    {
        $collection = new FieldCollection();
        (new AgenticDiscoverySalesChannelExtension())->extendFields($collection);

        $association = null;
        foreach ($collection as $field) {
            if ($field instanceof OneToOneAssociationField && $field->getPropertyName() === 'agenticDiscoveryConfig') {
                $association = $field;
                break;
            }
        }

        static::assertInstanceOf(OneToOneAssociationField::class, $association);
        static::assertSame(
            AgenticDiscoverySalesChannelConfigDefinition::class,
            $association->getReferenceClass()
        );

        $hasCascade = false;
        foreach ($association->getFlags() as $flag) {
            if ($flag instanceof CascadeDelete) {
                $hasCascade = true;
                break;
            }
        }
        static::assertTrue($hasCascade, 'Reverse association must cascade-delete on sales channel removal');
    }
}
