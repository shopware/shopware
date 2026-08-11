<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SalesChannelRepository::class)]
class SalesChannelRepositoryTest extends TestCase
{
    #[DataProvider('criteriaDepthProvider')]
    public function testEveryNestedCriteriaIsProcessed(int $depth): void
    {
        // the root criteria plus every nested one, so no definition restriction is silently skipped
        static::assertSame($depth + 1, $this->countProcessedCriteria($depth));
    }

    public function testACriteriaAboveTheLimitIsRejected(): void
    {
        // answering it would mean returning data the remaining criteria never restricted
        $this->expectExceptionObject(SalesChannelException::tooManyNestedCriteria(100));

        $this->countProcessedCriteria(250);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function criteriaDepthProvider(): iterable
    {
        yield 'a single criteria' => [0];
        yield 'below the limit' => [10];
        yield 'at the limit' => [98];
    }

    /**
     * Builds a criteria nested `$depth` levels deep and returns how many of its criteria the
     * repository handed to the definition.
     */
    private function countProcessedCriteria(int $depth): int
    {
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

        return $processed;
    }
}

/**
 * An entity that associates itself, so the test can build a criteria of arbitrary depth.
 *
 * @internal
 */
class SelfReferencingSalesChannelDefinition extends EntityDefinition implements SalesChannelDefinitionInterface
{
    public const ENTITY_NAME = 'self_referencing_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('parent_id', 'parentId', self::class),
            new OneToManyAssociationField('children', self::class, 'parent_id'),
        ]);
    }
}
