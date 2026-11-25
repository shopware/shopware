<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\AppContextFactory;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RepositoryFacade::class)]
class RepositoryFacadeTest extends TestCase
{
    /**
     * @param 'search'|'searchIds'|'aggregate' $repositoryMethod
     * @param \Closure(RepositoryFacade): object $read
     * @param \Closure(Criteria, Context): object $result
     */
    #[DataProvider('readMethodProvider')]
    public function testReadUsesAFreshAppContextPerCall(string $repositoryMethod, \Closure $read, \Closure $result): void
    {
        $integrationId = Uuid::randomHex();
        $hookContext = Context::createDefaultContext();
        $hookContext->setRuleIds([Uuid::randomHex()]);
        $ruleIds = $hookContext->getRuleIds();

        /** @var list<Context> $validatedContexts */
        $validatedContexts = [];
        $criteriaValidator = $this->createMock(AclCriteriaValidator::class);
        $criteriaValidator->expects($this->exactly(2))
            ->method('validate')
            ->willReturnCallback(static function (string $entity, Criteria $criteria, Context $context) use (&$validatedContexts): array {
                $validatedContexts[] = $context;

                return [];
            });

        /** @var list<Context> $readContexts */
        $readContexts = [];
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method($repositoryMethod)
            ->willReturnCallback(static function (Criteria $criteria, Context $context) use (&$readContexts, $result): object {
                $readContexts[] = $context;

                return $result($criteria, $context);
            });

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);
        $registry->method('getByEntityName')->willReturn(new ProductDefinition());

        $facade = new RepositoryFacade(
            $registry,
            static::createStub(RequestCriteriaBuilder::class),
            $criteriaValidator,
            AppContextFactory::forApp($hookContext, Uuid::randomHex(), $integrationId, ['product:read']),
        );

        $read($facade);
        $readContexts[0]->setRuleIds([Uuid::randomHex()]);
        $read($facade);

        static::assertCount(2, $readContexts);
        static::assertSame($validatedContexts, $readContexts);
        static::assertNotSame($readContexts[0], $readContexts[1]);
        static::assertNotSame($hookContext, $readContexts[0]);
        static::assertSame($ruleIds, $readContexts[1]->getRuleIds());

        $source = $readContexts[1]->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertSame($integrationId, $source->getIntegrationId());
    }

    public static function readMethodProvider(): \Generator
    {
        yield 'search' => [
            'search',
            static fn (RepositoryFacade $facade): EntitySearchResult => $facade->search('product', []),
            static fn (Criteria $criteria, Context $context): EntitySearchResult => new EntitySearchResult('product', 0, new ProductCollection(), null, $criteria, $context),
        ];
        yield 'ids' => [
            'searchIds',
            static fn (RepositoryFacade $facade): IdSearchResult => $facade->ids('product', []),
            static fn (Criteria $criteria, Context $context): IdSearchResult => new IdSearchResult(0, [], $criteria, $context),
        ];
        yield 'aggregate' => [
            'aggregate',
            static fn (RepositoryFacade $facade): AggregationResultCollection => $facade->aggregate('product', []),
            static fn (): AggregationResultCollection => new AggregationResultCollection(),
        ];
    }
}
