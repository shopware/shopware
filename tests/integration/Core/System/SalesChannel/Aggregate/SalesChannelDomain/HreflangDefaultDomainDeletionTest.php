<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Aggregate\SalesChannelDomain;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class HreflangDefaultDomainDeletionTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    /**
     * @var EntityRepository<SalesChannelDomainCollection>
     */
    private EntityRepository $domainRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $this->domainRepository = static::getContainer()->get('sales_channel_domain.repository');

        $this->domainRepository->create([
            $this->domain('previous-default'),
            $this->domain('new-default'),
        ], $this->context);

        $this->salesChannelRepository->update([[
            'id' => TestDefaults::SALES_CHANNEL,
            'hreflangActive' => true,
            'hreflangDefaultDomainId' => $this->ids->get('previous-default'),
        ]], $this->context);
    }

    public function testSalesChannelSurvivesDeletionOfItsHreflangDefaultDomain(): void
    {
        $this->domainRepository->delete([['id' => $this->ids->get('previous-default')]], $this->context);

        $salesChannel = $this->loadSalesChannel();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        static::assertNull($salesChannel->getHreflangDefaultDomainId());
    }

    public function testDeletingTheHreflangDefaultDomainReportsTheClearedDefaultOnTheSalesChannel(): void
    {
        $event = $this->domainRepository->delete([['id' => $this->ids->get('previous-default')]], $this->context);

        static::assertSame(
            [TestDefaults::SALES_CHANNEL],
            $event->getPrimaryKeysWithPropertyChange(SalesChannelDefinition::ENTITY_NAME, ['hreflangDefaultDomainId'])
        );
    }

    public function testHreflangDefaultDomainCanBeReplacedAndDeletedInOneSync(): void
    {
        static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('delete', SalesChannelDomainDefinition::ENTITY_NAME, SyncOperation::ACTION_DELETE, [
                ['id' => $this->ids->get('previous-default')],
            ]),
            new SyncOperation('write', SalesChannelDefinition::ENTITY_NAME, SyncOperation::ACTION_UPSERT, [
                ['id' => TestDefaults::SALES_CHANNEL, 'hreflangDefaultDomainId' => $this->ids->get('new-default')],
            ]),
        ], $this->context, new SyncBehavior());

        $salesChannel = $this->loadSalesChannel();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        static::assertSame($this->ids->get('new-default'), $salesChannel->getHreflangDefaultDomainId());
        static::assertNull(
            $this->domainRepository->searchIds(new Criteria([$this->ids->get('previous-default')]), $this->context)->firstId()
        );
    }

    public function testDeletingASalesChannelAlsoDeletesItsHreflangDefaultDomain(): void
    {
        $this->createSalesChannel([
            'id' => $this->ids->get('other-sales-channel'),
            'domains' => [['id' => $this->ids->get('own-default'), 'url' => 'http://own-default.hreflang.test']],
        ]);
        $this->salesChannelRepository->update([[
            'id' => $this->ids->get('other-sales-channel'),
            'hreflangActive' => true,
            'hreflangDefaultDomainId' => $this->ids->get('own-default'),
        ]], $this->context);

        $event = $this->salesChannelRepository->delete([['id' => $this->ids->get('other-sales-channel')]], $this->context);

        static::assertSame([$this->ids->get('other-sales-channel')], $event->getPrimaryKeys(SalesChannelDefinition::ENTITY_NAME));
        static::assertSame([$this->ids->get('other-sales-channel')], $event->getDeletedPrimaryKeys(SalesChannelDefinition::ENTITY_NAME));
        static::assertNull(
            $this->salesChannelRepository->searchIds(new Criteria([$this->ids->get('other-sales-channel')]), $this->context)->firstId()
        );
        static::assertNull(
            $this->domainRepository->searchIds(new Criteria([$this->ids->get('own-default')]), $this->context)->firstId()
        );
    }

    private function loadSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannelRepository
            ->search(new Criteria([TestDefaults::SALES_CHANNEL]), $this->context)
            ->getEntities()
            ->first();
    }

    /**
     * @return array<string, string|null>
     */
    private function domain(string $key): array
    {
        return [
            'id' => $this->ids->get($key),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'url' => \sprintf('http://%s.hreflang.test', $key),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
        ];
    }
}
