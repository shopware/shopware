<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler;
use Shopware\Core\Content\Sitemap\ScheduledTask\SitemapMessage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('sales-channel')]
class SitemapGenerateTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;
    use SalesChannelFunctionalTestBehaviour;

    private SitemapGenerateTaskHandler $sitemapHandler;

    private EntityRepository $salesChannelDomainRepository;

    private EntityRepository $salesChannelRepository;

    private MockObject&MessageBusInterface $messageBusMock;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->sitemapHandler = new SitemapGenerateTaskHandler(
            $this->getContainer()->get('scheduled_task.repository'),
            $this->salesChannelRepository,
            $this->getContainer()->get(SystemConfigService::class),
            $this->messageBusMock,
            $this->getContainer()->get('event_dispatcher')
        );
        $this->salesChannelDomainRepository = $this->getContainer()->get('sales_channel_domain.repository');
    }

    public function testNotHandelDuplicateWithSameLanguage(): void
    {
        /** @var list<string> $salesChannelIds */
        $salesChannelIds = $this->salesChannelRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();

        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test-sitemap-task-handler');

        $nonDefaults = array_values(array_filter(array_map(function (string $id): ?array {
            if ($id === TestDefaults::SALES_CHANNEL) {
                return null;
            }

            return ['id' => $id];
        }, $salesChannelIds)));

        $this->salesChannelRepository->delete($nonDefaults, Context::createDefaultContext());

        $this->salesChannelDomainRepository->create([
            [
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.com',
            ],
            [
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.de',
            ],
        ], Context::createDefaultContext());

        $message = new SitemapMessage(
            TestDefaults::SALES_CHANNEL,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            true
        );

        $this->messageBusMock->expects(static::once())
            ->method('dispatch')
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }

    public function testItGeneratesCorrectMessagesIfLastLanguageIsFirstOfNextSalesChannel(): void
    {
        /** @var list<string> $salesChannelIds */
        $salesChannelIds = $this->salesChannelRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();

        $nonDefaults = array_values(array_filter(array_map(function (string $id): ?array {
            if ($id === TestDefaults::SALES_CHANNEL) {
                return null;
            }

            return ['id' => $id];
        }, $salesChannelIds)));

        $this->salesChannelRepository->delete($nonDefaults, Context::createDefaultContext());

        $newSalesChannelId = Uuid::randomHex();
        while ($newSalesChannelId < TestDefaults::SALES_CHANNEL) {
            $newSalesChannelId = Uuid::randomHex();
        }

        $paymentMethod = $this->getAvailablePaymentMethod();

        $this->salesChannelDomainRepository->create([
            [
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.com',
            ],
            [
                'salesChannelId' => $newSalesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.de',
                'salesChannel' => [
                    'id' => $newSalesChannelId,
                    'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                    'name' => 'Test',
                    'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'currencyId' => Defaults::CURRENCY,
                    'paymentMethodId' => $paymentMethod->getId(),
                    'paymentMethods' => [['id' => $paymentMethod->getId()]],
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'navigationCategoryId' => $this->getValidCategoryId(),
                    'countryId' => $this->getValidCountryId(null),
                    'currencies' => [['id' => Defaults::CURRENCY]],
                    'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                    'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                ],
            ],
        ], Context::createDefaultContext());

        $message = new SitemapMessage(
            TestDefaults::SALES_CHANNEL,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            true
        );

        $this->messageBusMock->expects(static::once())
            ->method('dispatch')
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }

    public function testSkipNonStorefrontSalesChannels(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM sales_channel');

        $storefrontId = Uuid::randomHex();
        $this->createSalesChannel([
            'id' => $storefrontId,
            'name' => 'storefront',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://valid.test',
            ]],
        ]);
        $this->createSalesChannel([
            'name' => 'api',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://api.test',
            ]],
        ]);
        $this->createSalesChannel([
            'name' => 'export',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://export.test',
            ]],
        ]);

        $message = new SitemapMessage(
            $storefrontId,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            false
        );

        $this->messageBusMock->expects(static::once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }
}
