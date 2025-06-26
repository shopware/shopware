<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Generator\PropertyGroupGenerator;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(PropertyGroupGenerator::class)]
class PropertyGroupGeneratorTest extends TestCase
{
    public function testGetDefinition(): void
    {
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $generator = new PropertyGroupGenerator($propertyGroupRepository);

        static::assertSame(PropertyGroupDefinition::class, $generator->getDefinition());
    }

    public function testGenerateReturnsEarlyWhenNoItemsRequestedAndGroupsExist(): void
    {
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $idSearchResult = new IdSearchResult(5, [], new Criteria(), Context::createDefaultContext());

        $propertyGroupRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        $propertyGroupRepository->expects($this->never())
            ->method('create');

        $context = $this->createDemodataContext();

        $generator = new PropertyGroupGenerator($propertyGroupRepository);
        $generator->generate(0, $context);
    }

    public function testGenerateCreatesRequestedNumberOfPropertyGroups(): void
    {
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $idSearchResult = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());

        $propertyGroupRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        // Track calls to create method
        $createdGroups = [];

        $propertyGroupRepository->expects($this->exactly(3))
            ->method('create')
            ->willReturnCallback(function ($data) use (&$createdGroups) {
                $createdGroups[] = $data[0]['name'];
                // Return a mock EntityWrittenContainerEvent
                $context = Context::createDefaultContext();

                return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
            });

        $context = $this->createDemodataContext();

        $generator = new PropertyGroupGenerator($propertyGroupRepository);
        $generator->generate(3, $context);

        // Assert the first 3 groups are created in order
        static::assertEquals(['color', 'shoe-color', 'skin'], $createdGroups);
    }

    public function testGenerateCreatesUniqueGroupNames(): void
    {
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $idSearchResult = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());

        $propertyGroupRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        $createdGroupNames = [];

        $propertyGroupRepository->expects($this->exactly(15))
            ->method('create')
            ->willReturnCallback(function ($data) use (&$createdGroupNames) {
                $name = $data[0]['name'];
                $createdGroupNames[] = $name;
                // Return a mock EntityWrittenContainerEvent
                $context = Context::createDefaultContext();

                return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
            });

        $context = $this->createDemodataContext();

        $generator = new PropertyGroupGenerator($propertyGroupRepository);
        $generator->generate(15, $context);

        // Test that we have 15 groups created
        static::assertCount(15, $createdGroupNames);

        // Test that all group names are unique
        static::assertCount(\count(array_unique($createdGroupNames)), $createdGroupNames);

        // We expect the first 11 to be the base names, then they start repeating with suffixes
        $baseNames = [
            'color', 'shoe-color', 'skin', 'shirt-color', 'length',
            'width', 'textile', 'content', 'size', 'shoe-size', 'shirt-size',
        ];

        foreach ($baseNames as $index => $baseName) {
            static::assertEquals($baseName, $createdGroupNames[$index]);
        }

        // Test that after the base names, we get numbered versions
        static::assertEquals('color_1', $createdGroupNames[11]);
        static::assertEquals('shoe-color_1', $createdGroupNames[12]);
        static::assertEquals('skin_1', $createdGroupNames[13]);
        static::assertEquals('shirt-color_1', $createdGroupNames[14]);
    }

    public function testCreatedGroupHasCorrectStructure(): void
    {
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $idSearchResult = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());

        $propertyGroupRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        $capturedData = null;

        $propertyGroupRepository->expects($this->once())
            ->method('create')
            ->willReturnCallback(function ($data) use (&$capturedData) {
                $capturedData = $data[0];
                // Return a mock EntityWrittenContainerEvent
                $context = Context::createDefaultContext();

                return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
            });

        $context = $this->createDemodataContext();

        $generator = new PropertyGroupGenerator($propertyGroupRepository);
        $generator->generate(1, $context);

        static::assertNotNull($capturedData);
        static::assertArrayHasKey('id', $capturedData);
        static::assertArrayHasKey('name', $capturedData);
        static::assertArrayHasKey('options', $capturedData);
        static::assertArrayHasKey('sorting_type', $capturedData);
        static::assertArrayHasKey('display_type', $capturedData);

        static::assertEquals(PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC, $capturedData['sorting_type']);
        static::assertEquals(PropertyGroupDefinition::DISPLAY_TYPE_TEXT, $capturedData['display_type']);

        // Check that options are properly structured
        static::assertIsArray($capturedData['options']);
        static::assertNotEmpty($capturedData['options']);

        foreach ($capturedData['options'] as $option) {
            static::assertArrayHasKey('id', $option);
            static::assertArrayHasKey('name', $option);
            static::assertTrue(Uuid::isValid($option['id']));
        }
    }

    public function testGenerateCallsProgressMethods(): void
    {
        $propertyGroupRepository = $this->createMock(EntityRepository::class);
        $idSearchResult = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());

        $propertyGroupRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        $propertyGroupRepository->expects($this->exactly(3))
            ->method('create')
            ->willReturnCallback(function () {
                $context = Context::createDefaultContext();

                return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
            });

        $console = $this->createMock(SymfonyStyle::class);
        $console->expects($this->once())->method('progressStart')->with(3);
        $console->expects($this->exactly(3))->method('progressAdvance')->with(1);
        $console->expects($this->once())->method('progressFinish');

        $context = $this->createDemodataContext($console);

        $generator = new PropertyGroupGenerator($propertyGroupRepository);
        $generator->generate(3, $context);
    }

    private function createDemodataContext(?SymfonyStyle $console = null): DemodataContext
    {
        $context = Context::createDefaultContext();
        $console = $console ?? $this->createMock(SymfonyStyle::class);
        $faker = Factory::create();
        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        return new DemodataContext(
            $context,
            $faker,
            '/test',
            $console,
            $registry
        );
    }
}
