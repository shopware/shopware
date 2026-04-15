<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MailDataProvider::class)]
#[Package('after-sales')]
class MailDataProviderTest extends TestCase
{
    public function testGetTemplateDataFiltersUnavailableEntitiesAndUsesProviderEntityName(): void
    {
        $context = Context::createDefaultContext();
        $orderEntity = new MailTemplateEntity();
        $provider = $this->createProvider('order', $orderEntity);

        $mailDataProvider = new MailDataProvider([
            'order' => $provider,
        ]);

        $result = $mailDataProvider->getTemplateData(
            $this->createMailTemplate(['order' => 'order']),
            [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            $context
        );

        static::assertSame(['order' => $orderEntity], $result);
        static::assertSame(['order-id'], $provider->requestedIds);
        static::assertSame([$context], $provider->requestedContexts);
    }

    public function testGetTemplateDataReturnsInjectedTemplateDataWhenNoMailTemplateTypeExists(): void
    {
        $mailDataProvider = new MailDataProvider([]);

        $result = $mailDataProvider->getTemplateData(
            new MailTemplateEntity(),
            ['order' => 'order-id'],
            Context::createDefaultContext(),
            ['foo' => 'bar']
        );

        static::assertSame(['foo' => 'bar'], $result);
    }

    public function testGetTemplateDataAllowsInjectedTemplateDataToOverrideProvidedEntities(): void
    {
        $context = Context::createDefaultContext();
        $providerEntity = new MailTemplateEntity();
        $provider = $this->createProvider('order', $providerEntity);

        $mailDataProvider = new MailDataProvider([
            'order' => $provider,
        ]);

        $result = $mailDataProvider->getTemplateData(
            $this->createMailTemplate(['order' => 'order']),
            ['order' => 'order-id'],
            $context,
            ['order' => 'overridden', 'extra' => 'value']
        );

        static::assertSame(
            [
                'order' => 'overridden',
                'extra' => 'value',
            ],
            $result
        );
    }

    public function testGetTemplateDataThrowsWhenProviderIsMissingForAvailableEntity(): void
    {
        $mailDataProvider = new MailDataProvider([]);

        $this->expectExceptionObject(MailTemplateException::missingDataProvider('order'));

        $mailDataProvider->getTemplateData(
            $this->createMailTemplate(['order' => 'order']),
            ['order' => 'order-id'],
            Context::createDefaultContext(),
        );
    }

    /**
     * @param array<string, mixed>|null $availableEntities
     */
    private function createMailTemplate(?array $availableEntities): MailTemplateEntity
    {
        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setAvailableEntities($availableEntities);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateType($mailTemplateType);

        return $mailTemplate;
    }

    private function createProvider(string $entityName, ?Entity $entity): TestMailFlowProvider
    {
        return new TestMailFlowProvider(
            $entityName,
            $entity,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ContainerInterface::class)
        );
    }
}

/**
 * @internal
 *
 * @extends AbstractProvider<Entity, EntityCollection<Entity>>
 */
class TestMailFlowProvider extends AbstractProvider
{
    /**
     * @var list<string>
     */
    public array $requestedIds = [];

    /**
     * @var list<Context>
     */
    public array $requestedContexts = [];

    public function __construct(
        private readonly string $entityName,
        private readonly ?Entity $entity,
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ) {
        parent::__construct($eventDispatcher, $container);
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getData(string $entityId, Context $context): ?Entity
    {
        $this->requestedIds[] = $entityId;
        $this->requestedContexts[] = $context;

        return $this->entity;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
