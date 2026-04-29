<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @template TProvider of AbstractProvider
 */
abstract class AbstractProviderTestCase extends TestCase
{
    final public function testCanGetRepository(): void
    {
        $repository = $this->createMock(EntityRepository::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects($this->once())
            ->method('get')
            ->with($this->getEntityName() . '.repository')
            ->willReturn($repository);

        $provider = $this->createProvider(
            $this->createMock(EventDispatcherInterface::class),
            $container,
        );

        $repository
            ->expects($this->once())
            ->method('search');

        $provider->getData('some-id', Context::createDefaultContext());
    }

    final public function testDispatchesCriteriaEvent(): void
    {
        $repository = $this->createMock(EntityRepository::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturn($repository);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(function ($event) {
                    return $event instanceof MailFlowDataCriteriaEvent
                        && $event->entityName === $this->getEntityName();
                }),
                'mail-flow.data.' . $this->getEntityName() . '.criteria.event'
            );

        $provider = $this->createProvider($eventDispatcher, $container);

        $provider->getCriteria('some-id', Context::createDefaultContext());
    }

    final public function testLoadsExpectedAssociations(): void
    {
        $provider = $this->createProvider(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ContainerInterface::class),
        );

        $criteria = $provider->getCriteria('some-id', Context::createDefaultContext());

        foreach ($this->getExpectedAssociations() as $association) {
            $this->assertCriteriaHasAssociationPath($criteria, $association);
        }

        if ($this->getExpectedAssociations() === []) {
            static::assertSame([], array_keys($criteria->getAssociations()));
        }

        $this->assertAdditionalCriteria($criteria);
    }

    /**
     * @return list<string>
     */
    protected function getExpectedAssociations(): array
    {
        return [];
    }

    protected function assertAdditionalCriteria(Criteria $criteria): void
    {
    }

    /**
     * @return TProvider
     */
    abstract protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): AbstractProvider;

    abstract protected function getEntityName(): string;

    private function assertCriteriaHasAssociationPath(Criteria $criteria, string $associationPath): void
    {
        $current = $criteria;

        foreach (explode('.', $associationPath) as $part) {
            static::assertArrayHasKey($part, $current->getAssociations());

            $current = $current->getAssociations()[$part];
        }
    }
}
