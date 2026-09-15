<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopware\Core\Checkout\DocumentV2\DocumentEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('after-sales')]
class LegacyClassAliasTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return iterable<string, array{legacy: string, current: class-string}>
     */
    public static function legacyServiceIdProvider(): iterable
    {
        yield 'DocumentDefinition' => [
            'legacy' => 'Shopware\\Core\\Checkout\\Document\\DocumentDefinition',
            'current' => DocumentDefinition::class,
        ];

        yield 'DocumentBaseConfigDefinition' => [
            'legacy' => 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition',
            'current' => DocumentBaseConfigDefinition::class,
        ];

        yield 'DocumentBaseConfigSalesChannelDefinition' => [
            'legacy' => 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition',
            'current' => DocumentBaseConfigSalesChannelDefinition::class,
        ];
    }

    #[DataProvider('legacyServiceIdProvider')]
    public function testPreviousServiceIdResolvesToTheSameInstance(string $legacy, string $current): void
    {
        $container = static::getContainer();

        static::assertTrue($container->has($legacy));
        static::assertSame($container->get($current), $container->get($legacy));
    }

    public function testDefinitionRegistryResolvesThePreviousDefinitionName(): void
    {
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        static::assertInstanceOf(DefinitionInstanceRegistry::class, $registry);

        $definition = $registry->get('Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition');

        static::assertSame(DocumentBaseConfigSalesChannelDefinition::class, $definition::class);
        static::assertSame('document_base_config_sales_channel', $definition->getEntityName());
        static::assertSame(DocumentBaseConfigSalesChannelEntity::class, $definition->getEntityClass());
    }

    public function testDocumentDefinitionStillDescribesTheDocumentEntity(): void
    {
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        static::assertInstanceOf(DefinitionInstanceRegistry::class, $registry);

        $definition = $registry->getByEntityName('document');

        static::assertSame(DocumentDefinition::class, $definition::class);
        static::assertSame(DocumentEntity::class, $definition->getEntityClass());
    }
}
