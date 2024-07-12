<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\Extension\UserExtension;
use Shopware\Administration\Notification\NotificationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserDefinition;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(UserExtension::class)]
class UserExtensionTest extends TestCase
{
    private UserExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new UserExtension();
    }

    public function testExtendFieldsAddsOneAssociation(): void
    {
        $collection = new FieldCollection();

        $this->extension->extendFields($collection);

        static::assertCount(1, $collection);
        $associationField = $collection->first();
        static::assertInstanceOf(OneToManyAssociationField::class, $associationField);
        static::assertSame('created_by_user_id', $associationField->getReferenceField());
        static::assertSame(NotificationDefinition::class, $associationField->getReferenceClass());
    }

    public function testGetDefinitionClassIsDefined(): void
    {
        static::assertSame(UserDefinition::class, $this->extension->getDefinitionClass());
    }

    public function testExtendProtectionsIsUntouched(): void
    {
        $protections = new EntityProtectionCollection([]);

        $this->extension->extendProtections($protections);
        static::assertCount(0, $protections);
    }
}
