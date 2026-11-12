<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Generator;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\Generator\PropertyGroupGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PropertyGroupGenerator::class)]
class PropertyGroupGeneratorTest extends TestCase
{
    public function testCreatesTheRequestedNumberOfGroups(): void
    {
        $repository = StaticEntityRepository::of(PropertyGroupCollection::class, [[]]);

        (new PropertyGroupGenerator($repository))->generate(13, $this->createContext());

        static::assertSame(
            ['color', 'shoe-color', 'skin', 'shirt-color', 'length', 'width', 'textile', 'content', 'size', 'shoe-size', 'shirt-size', 'color 2', 'shoe-color 2'],
            array_column($this->createdGroups($repository), 'name'),
        );
    }

    public function testOnlyAddsTheGroupsThatAreMissing(): void
    {
        $repository = StaticEntityRepository::of(PropertyGroupCollection::class, [[Uuid::randomHex(), Uuid::randomHex()]]);

        (new PropertyGroupGenerator($repository))->generate(4, $this->createContext());

        static::assertSame(['skin', 'shirt-color'], array_column($this->createdGroups($repository), 'name'));
    }

    public function testCreatesNothingWhenEnoughGroupsExist(): void
    {
        $repository = StaticEntityRepository::of(PropertyGroupCollection::class, [[Uuid::randomHex(), Uuid::randomHex()]]);

        (new PropertyGroupGenerator($repository))->generate(2, $this->createContext());

        static::assertSame([], $repository->creates);
    }

    public function testCreatesTheRequestedNumberOfOptionsPerGroup(): void
    {
        $repository = StaticEntityRepository::of(PropertyGroupCollection::class, [[]]);

        (new PropertyGroupGenerator($repository))->generate(7, $this->createContext(), ['options' => 7]);

        $textile = $this->createdGroups($repository)[6];
        static::assertSame('textile', $textile['name']);
        static::assertSame(
            ['cotton', 'linen', 'wool', 'leather', 'silk', 'cotton 2', 'linen 2'],
            array_column($textile['options'], 'name'),
        );
    }

    private function createContext(): DemodataContext
    {
        $context = static::createStub(DemodataContext::class);
        $context->method('getFaker')->willReturn(Factory::create());
        $context->method('getConsole')->willReturn(static::createStub(SymfonyStyle::class));

        return $context;
    }

    /**
     * @param StaticEntityRepository<PropertyGroupCollection> $repository
     *
     * @return list<array{name: string, options: list<array{name: string}>}>
     */
    private function createdGroups(StaticEntityRepository $repository): array
    {
        /** @var list<array{name: string, options: list<array{name: string}>}> $groups */
        $groups = array_merge(...$repository->creates);

        return $groups;
    }
}
