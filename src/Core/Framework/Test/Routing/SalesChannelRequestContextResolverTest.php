<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelRequestContextResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

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

        $phpunit = $this;
        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $this->ids->get('sales-channel'));
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, new RouteScope(['scopes' => ['storefront']]));

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerContextEventClosure = function (SalesChannelContextResolvedEvent $event) use (&$eventDidRun, $phpunit, $currencyId): void {
            $eventDidRun = true;
            $phpunit->assertSame($currencyId, $event->getSalesChannelContext()->getContext()->getCurrencyId());
        };

        $dispatcher->addListener(SalesChannelContextResolvedEvent::class, $listenerContextEventClosure);

        $resolver->resolve($request);

        $dispatcher->removeListener(SalesChannelContextResolvedEvent::class, $listenerContextEventClosure);

        static::assertTrue($eventDidRun, 'The "' . SalesChannelContextResolvedEvent::class . '" Event did not run');
    }

    /**
     * @dataProvider domainData
     */
    public function testContextCurrency(string $url, string $currencyCode, string $expectedCode): void
    {
        $currencyId = $this->getCurrencyId($currencyCode);
        $expectedCurrencyId = $expectedCode !== $currencyCode ? $this->getCurrencyId($expectedCode) : $currencyId;

        $context = $this->contextService->get($this->ids->get('sales-channel'), $this->ids->get('token'), Defaults::LANGUAGE_SYSTEM, $currencyId);

        static::assertSame($expectedCurrencyId, $context->getContext()->getCurrencyId());
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
