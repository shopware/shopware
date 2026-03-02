<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\EnumProviderRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;

/**
 * @internal
 */
#[CoversClass(EnumProviderRegistry::class)]
class EnumProviderRegistryTest extends TestCase
{
    public function testReturnsStaticChoicesWhenNoProviderMatches(): void
    {
        $definition = $this->createConfiguredMock(EntityDefinition::class, [
            'getEntityName' => 'product',
        ]);

        $field = (new StringField('status', 'status'))->addFlags(new Choice(['draft', 'live']));

        $registry = new EnumProviderRegistry([
            new class implements FieldEnumProviderInterface {
                public function isSupported(string $entity, string $fieldName): bool
                {
                    return false;
                }

                public function getChoices(): array
                {
                    return ['archived'];
                }
            },
        ]);

        static::assertSame(['draft', 'live'], $registry->getChoices($definition, $field));
    }

    public function testMergesStaticAndDynamicChoicesUniquely(): void
    {
        $definition = $this->createConfiguredMock(EntityDefinition::class, [
            'getEntityName' => 'product',
        ]);

        $field = (new StringField('status', 'status'))->addFlags(new Choice(['draft', 'live']));

        $registry = new EnumProviderRegistry([
            new class implements FieldEnumProviderInterface {
                public function isSupported(string $entity, string $fieldName): bool
                {
                    return $entity === 'product' && $fieldName === 'status';
                }

                public function getChoices(): array
                {
                    return ['live', 'archived'];
                }
            },
        ]);

        static::assertSame(['draft', 'live', 'archived'], $registry->getChoices($definition, $field));
    }
}
