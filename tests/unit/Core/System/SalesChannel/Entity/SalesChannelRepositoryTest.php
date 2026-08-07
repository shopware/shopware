<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\System\SalesChannel\Entity\_fixtures\SelfReferencingSalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SalesChannelRepository::class)]
class SalesChannelRepositoryTest extends TestCase
{
    public function testEveryNestedCriteriaIsProcessedRegardlessOfTheTreeSize(): void
    {
        $depth = 250;

        $criteria = new Criteria();
        $nested = $criteria;
        for ($i = 0; $i < $depth; ++$i) {
            $nested = $nested->getAssociation('children');
        }

        $registry = new StaticDefinitionInstanceRegistry(
            [new SelfReferencingSalesChannelDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $dispatcher = new EventDispatcher();
        $processed = 0;
        $dispatcher->addListener(
            \sprintf('sales_channel.%s.process.criteria', SelfReferencingSalesChannelDefinition::ENTITY_NAME),
            function () use (&$processed): void {
                ++$processed;
            }
        );

        /** @var SalesChannelRepository<EntityCollection<Entity>> $repository */
        $repository = new SalesChannelRepository(
            $registry->getByEntityName(SelfReferencingSalesChannelDefinition::ENTITY_NAME),
            static::createStub(EntityReaderInterface::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $dispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $repository->searchIds($criteria, $context);

        // the root criteria plus every nested one, so no definition restriction is silently skipped
        static::assertSame($depth + 1, $processed);
    }
}
