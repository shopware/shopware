<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelRequestContextResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    private $salesChannel;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->repository = $this->getContainer()->get('currency.repository');
        $this->contextService = $this->getContainer()->get(SalesChannelContextService::class);

        $this->createTestSalesChannel();
    }

    public function testRequestSalesChannelCurrency(): void
    {
        $resolver = $this->getContainer()->get(SalesChannelRequestContextResolver::class);

        $currencyId = Uuid::randomHex();

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addListener(SalesChannelContextResolvedEvent::class, function (SalesChannelContextResolvedEvent $event) use ($currencyId): void {
            static::assertSame($currencyId, $event->getSalesChannelContext()->getCurrency()->getId());
        });

        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);

        $resolver->resolve($request);
    }

    /**
     * @dataProvider domainData
     */
    public function testContextCurrency(string $url, string $currencyCode, string $expectedCode): void
    {
        $currencyId = $this->getCurrencyId($currencyCode);
        $expectedCurrencyId = $expectedCode !== $currencyCode ? $this->getCurrencyId($expectedCode) : $currencyId;

        $context = $this->contextService->get($this->ids->get('sales-channel'), $this->ids->get('token'), null, $currencyId);

        static::assertSame($expectedCurrencyId, $context->getCurrency()->getId());
    }

    public function domainData(): array
    {
        return [
            [
                'http://test.store/en-eur',
                'EUR',
                'EUR',
            ],
            [
                'http://test.store/en-usd',
                'USD',
                'USD',
            ],
        ];
    }

    private function getCurrencyId(string $isoCode): ?string
    {
        $currency = $this->repository->search(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)),
            Context::createDefaultContext()
        )->first();

        return $currency !== null ? $currency->getId() : null;
    }

    private function createTestSalesChannel(): void
    {
        $usdCurrencyId = $this->getCurrencyId('USD');

        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel'),
            'domains' => [
                [
                    'id' => $this->ids->get('eur-domain'),
                    'url' => 'http://test.store/en-eur',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
                [
                    'id' => $this->ids->get('usd-domain'),
                    'url' => 'http://test.store/en-usd',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => $usdCurrencyId,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
            ],
        ]);
    }
}
