<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\CustomEntity\Schema\DynamicMappingEntityDefinition;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DynamicMappingEntityDefinition::class)]
class DynamicMappingEntityDefinitionTest extends TestCase
{
    public function testCreateExposesTheEntityName(): void
    {
        $definition = DynamicMappingEntityDefinition::create('tax', 'currency', 'custom_entity_tax_currency');

        static::assertSame('custom_entity_tax_currency', $definition->getEntityName());
    }

    public function testDefineFieldsBuildsTheMappingFields(): void
    {
        $definition = DynamicMappingEntityDefinition::create('tax', 'currency', 'custom_entity_tax_currency');

        new StaticDefinitionInstanceRegistry(
            [TaxDefinition::class, CurrencyDefinition::class, $definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $fields = $definition->getFields();

        static::assertInstanceOf(FkField::class, $fields->get('taxId'));
        static::assertInstanceOf(FkField::class, $fields->get('currencyId'));
        static::assertInstanceOf(ManyToOneAssociationField::class, $fields->get('tax'));
        static::assertInstanceOf(ManyToOneAssociationField::class, $fields->get('currency'));
    }
}
