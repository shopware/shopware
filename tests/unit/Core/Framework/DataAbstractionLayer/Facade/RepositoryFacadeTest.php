<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\AppContextFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RepositoryFacade::class)]
class RepositoryFacadeTest extends TestCase
{
    public function testIdsSearchesWithAFreshAppContextPerCall(): void
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

        $repository = StaticEntityRepository::of(ProductCollection::class, [[Uuid::randomHex()], [Uuid::randomHex()]]);
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);
        $registry->method('getByEntityName')->willReturn(new ProductDefinition());

        $facade = new RepositoryFacade(
            $registry,
            static::createStub(RequestCriteriaBuilder::class),
            $criteriaValidator,
            AppContextFactory::forApp($hookContext, $integrationId, ['product:read']),
        );

        $first = $facade->ids('product', [])->getContext();
        $first->setRuleIds([Uuid::randomHex()]);
        $second = $facade->ids('product', [])->getContext();

        static::assertCount(2, $validatedContexts);
        static::assertSame($validatedContexts[0], $first);
        static::assertSame($validatedContexts[1], $second);
        static::assertNotSame($first, $second);
        static::assertNotSame($hookContext, $first);
        static::assertSame($ruleIds, $second->getRuleIds());

        $source = $second->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertSame($integrationId, $source->getIntegrationId());
    }
}
