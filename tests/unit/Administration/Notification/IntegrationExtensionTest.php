<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\Extension\IntegrationExtension;
use Shopware\Administration\Notification\NotificationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationDefinition;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(IntegrationExtension::class)]
class IntegrationExtensionTest extends TestCase
{
    private IntegrationExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new IntegrationExtension();
    }

    public function testExtendFieldsAddsOneAssociation(): void
    {
        $collection = new FieldCollection();

        $this->extension->extendFields($collection);

        static::assertCount(1, $collection);
        $associationField = $collection->first();
        static::assertInstanceOf(OneToManyAssociationField::class, $associationField);
        static::assertSame('created_by_integration_id', $associationField->getReferenceField());
        static::assertSame(NotificationDefinition::class, $associationField->getReferenceClass());
    }

    public function testGetDefinitionClassIsDefined(): void
    {
        static::assertSame(IntegrationDefinition::class, $this->extension->getDefinitionClass());
    }

    public function testExtendProtectionsIsUntouched(): void
    {
        $protections = new EntityProtectionCollection([]);

        $this->extension->extendProtections($protections);
        static::assertCount(0, $protections);
    }
}
