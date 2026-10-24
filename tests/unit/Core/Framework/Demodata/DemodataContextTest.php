<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata;

use Faker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DemodataContext::class)]
class DemodataContextTest extends TestCase
{
    public function testGetIdsSearchesOnFirstCall(): void
    {
        $context = $this->createContext(StaticEntityRepository::of(EntityCollection::class, [['id-1', 'id-2']]));

        static::assertSame(['id-1', 'id-2'], $context->getIds('product'));
    }

    public function testGetIdsServesRepeatedCallsFromTheCache(): void
    {
        // a second search would exhaust the single queued result and throw
        $context = $this->createContext(StaticEntityRepository::of(EntityCollection::class, [['id-1']]));

        static::assertSame(['id-1'], $context->getIds('product'));
        static::assertSame(['id-1'], $context->getIds('product'));
    }

    public function testGetIdsCachesPerEntity(): void
    {
        $context = $this->createContext(StaticEntityRepository::of(EntityCollection::class, [['product-1'], ['tax-1']]));

        static::assertSame(['product-1'], $context->getIds('product'));
        static::assertSame(['tax-1'], $context->getIds('tax'));
        static::assertSame(['product-1'], $context->getIds('product'));
    }

    public function testGetIdsSearchesAgainAfterAnEmptyResult(): void
    {
        $context = $this->createContext(StaticEntityRepository::of(EntityCollection::class, [[], ['id-1']]));

        static::assertSame([], $context->getIds('product'));
        static::assertSame(['id-1'], $context->getIds('product'));
    }

    /**
     * @param StaticEntityRepository<*> $repository
     */
    private function createContext(StaticEntityRepository $repository): DemodataContext
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        return new DemodataContext(
            Context::createDefaultContext(),
            static::createStub(Generator::class),
            '/tmp',
            static::createStub(SymfonyStyle::class),
            $registry
        );
    }
}
