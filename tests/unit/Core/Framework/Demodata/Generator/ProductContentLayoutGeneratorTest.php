<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(Connection::class),
        );

        static::assertSame(ProductContentLayoutDefinition::class, $generator->getDefinition());
    }

    public function testGenerateCreatesLayoutAndAssignmentForProducts(): void
    {
        $productId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([$productId]);

        $layoutPayload = null;
        $contentLayoutRepository = $this->createMock(EntityRepository::class);
        $contentLayoutRepository->expects($this->once())->method('create')->with(
            static::callback(static function (array $payload) use (&$layoutPayload): bool {
                $layoutPayload = $payload;

                return true;
            }),
            static::anything(),
        );

        $assignmentPayload = null;
        $productContentLayoutRepository = $this->createMock(EntityRepository::class);
        $productContentLayoutRepository->expects($this->once())->method('create')->with(
            static::callback(static function (array $payload) use (&$assignmentPayload): bool {
                $assignmentPayload = $payload;

                return true;
            }),
            static::anything(),
        );

        $console = $this->createMock(SymfonyStyle::class);
        $console->expects($this->once())->method('progressStart')->with(1);
        $console->expects($this->once())->method('progressAdvance');
        $console->expects($this->once())->method('progressFinish');

        $context = new DemodataContext(
            Context::createDefaultContext(),
            Factory::create(),
            __DIR__,
            $console,
            static::createStub(DefinitionInstanceRegistry::class),
        );

        (new ProductContentLayoutGenerator(
            $contentLayoutRepository,
            $productContentLayoutRepository,
            $connection,
        ))->generate(1, $context);

        static::assertIsArray($layoutPayload);
        static::assertCount(1, $layoutPayload);
        static::assertSame('product', $layoutPayload[0]['rootSource']);
        static::assertSame('1.0.0', $layoutPayload[0]['version']);
        static::assertIsArray($layoutPayload[0]['layout']);

        $textElement = $layoutPayload[0]['layout'][0]['slots']['content'][0];
        static::assertSame('Sw:Content:Text', $textElement['component']);
        static::assertNotEmpty($textElement['properties']['text']);

        $layoutId = $layoutPayload[0]['id'];

        static::assertIsArray($assignmentPayload);
        static::assertCount(1, $assignmentPayload);
        static::assertSame($productId, $assignmentPayload[0]['productId']);
        static::assertSame($layoutId, $assignmentPayload[0]['contentLayoutId']);
        static::assertNull($assignmentPayload[0]['salesChannelId']);
    }

    public function testGenerateDoesNothingWithoutProducts(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([]);

        $contentLayoutRepository = $this->createMock(EntityRepository::class);
        $contentLayoutRepository->expects($this->never())->method('create');

        $productContentLayoutRepository = $this->createMock(EntityRepository::class);
        $productContentLayoutRepository->expects($this->never())->method('create');

        (new ProductContentLayoutGenerator(
            $contentLayoutRepository,
            $productContentLayoutRepository,
            $connection,
        ))->generate(1, static::createStub(DemodataContext::class));
    }
}
