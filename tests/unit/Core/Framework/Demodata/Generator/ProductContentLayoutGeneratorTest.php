<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\ContentSystemLayoutProvisioner;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Generator\ProductContentLayoutGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductContentLayoutGenerator::class)]
class ProductContentLayoutGeneratorTest extends TestCase
{
    public function testGetDefinition(): void
    {
        $generator = new ProductContentLayoutGenerator(
            static::createStub(ContentSystemLayoutProvisioner::class),
            static::createStub(Connection::class),
        );

        static::assertSame(ProductContentLayoutDefinition::class, $generator->getDefinition());
    }

    public function testGenerateAppliesTheProductDetailPagePresetToEachProduct(): void
    {
        $productIds = [Uuid::randomHex(), Uuid::randomHex()];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn($productIds);

        $context = new DemodataContext(
            Context::createDefaultContext(),
            Factory::create(),
            __DIR__,
            static::createStub(SymfonyStyle::class),
            static::createStub(DefinitionInstanceRegistry::class),
        );

        $applied = [];
        $provisioner = $this->createMock(ContentSystemLayoutProvisioner::class);
        $provisioner->expects($this->exactly(2))
            ->method('provisionFromPreset')
            ->willReturnCallback(static function (string $name, string $presetId, string $rootSource, Context $applyContext, ?string $layoutId, ?string $productId) use (&$applied, $context): string {
                static::assertSame('product', $rootSource);
                static::assertNotSame('', $name);
                static::assertSame($context->getContext(), $applyContext);
                $applied[] = [$presetId, $layoutId, $productId];

                return Uuid::randomHex();
            });

        (new ProductContentLayoutGenerator($provisioner, $connection))->generate(2, $context);

        static::assertSame([
            ['Sw:ProductDetailPage', null, $productIds[0]],
            ['Sw:ProductDetailPage', null, $productIds[1]],
        ], $applied);
    }

    public function testGenerateDoesNothingWithoutProducts(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $provisioner = $this->createMock(ContentSystemLayoutProvisioner::class);
        $provisioner->expects($this->never())->method('provisionFromPreset');

        (new ProductContentLayoutGenerator($provisioner, $connection))->generate(1, static::createStub(DemodataContext::class));
    }
}
