<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\SalesChannel\CachedCountryRoute;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\CountryStateController;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPagelet;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletCriteriaEvent;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CountryStateControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private Connection $connection;

    private string $countryIdDE;

    private CountryStateController $countryStateController;

    private SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->countryIdDE = Uuid::fromBytesToHex(
            $this->connection->fetchAllAssociative('SELECT id FROM country WHERE iso = \'DE\'')[0]['id']
        );

        $this->countryStateController = $this->getContainer()->get(CountryStateController::class);

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testGetCountryData(): void
    {
        $response = $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->salesChannelContext);

        if (!Feature::isActive('v6.5.0.0')) {
            static::assertFalse(\json_decode((string) $response->getContent(), true)['stateRequired']);
        }
        static::assertCount(16, \json_decode((string) $response->getContent(), true)['states']);

        if (!Feature::isActive('v6.5.0.0')) {
            // Check state required
            $this->connection->executeStatement(
                'UPDATE country SET force_state_in_registration = 1',
                [
                    'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                    'countryId' => Uuid::fromHexToBytes($this->countryIdDE),
                ]
            );

            $this->getContainer()->get('cache.object')
                ->invalidateTags([CachedCountryRoute::buildName(TestDefaults::SALES_CHANNEL)]);

            $response = $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->salesChannelContext);

            $data = \json_decode((string) $response->getContent(), true);
            static::assertArrayHasKey('zipcodeRequired', $data);
            static::assertArrayHasKey('stateRequired', $data);
            static::assertTrue($data['stateRequired']);
        }

        // Check empty CountryId

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('Parameter countryId is empty');
        $response = $this->countryStateController->getCountryData(new Request([], ['countryId' => null]), $this->salesChannelContext);

        if (!Feature::isActive('v6.5.0.0')) {
            $data = \json_decode((string) $response->getContent(), true);
            static::assertArrayHasKey('zipcodeRequired', $data);
            static::assertArrayHasKey('stateRequired', $data);
            static::assertArrayNotHasKey('states', $data);
            static::assertTrue($data['stateRequired']);
        }
    }

    public function testCountryStateControllerEvents(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $testSubscriber = new CountryStateControllerTestSubscriber();
        $dispatcher->addSubscriber($testSubscriber);

        $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->salesChannelContext);

        $dispatcher->removeSubscriber($testSubscriber);

        static::assertInstanceOf(CountryStateDataPagelet::class, $testSubscriber::$testPagelet);
        static::assertInstanceOf(Criteria::class, $testSubscriber::$criteriaEvent->getCriteria());
        static::assertInstanceOf(Context::class, $testSubscriber::$criteriaEvent->getContext());
        static::assertInstanceOf(Request::class, $testSubscriber::$criteriaEvent->getRequest());
        static::assertInstanceOf(SalesChannelContext::class, $testSubscriber::$criteriaEvent->getSalesChannelContext());
    }

    public function testCountryStateControllerHooks(): void
    {
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        $this->getContainer()->get('app.repository')->create([[
            'id' => $appId,
            'name' => 'Test',
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'id' => $integrationId,
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'Test',
            ],
            'scripts' => [
                [
                    'name' => 'country-loaded/loaded.script.twig',
                    'hook' => 'country-sate-data-pagelet-loaded',
                    'script' => '{% do debug.dump(hook.getPage.getStates.count) %}',
                    'active' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->salesChannelContext);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CountryStateDataPageletLoadedHook::HOOK_NAME, $traces);

        static::assertEquals(['16'], $traces['country-sate-data-pagelet-loaded'][0]['output']);
    }
}

/**
 * @internal
 */
class CountryStateControllerTestSubscriber implements EventSubscriberInterface
{
    public static CountryStateDataPagelet $testPagelet;

    public static CountryStateDataPageletCriteriaEvent $criteriaEvent;

    public static function getSubscribedEvents(): array
    {
        return [
            CountryStateDataPageletLoadedEvent::class => 'onPageletLoaded',
            CountryStateDataPageletCriteriaEvent::class => 'onCriteria',
        ];
    }

    public function onPageletLoaded(CountryStateDataPageletLoadedEvent $event): void
    {
        self::$testPagelet = $event->getPagelet();
    }

    public function onCriteria(CountryStateDataPageletCriteriaEvent $event): void
    {
        self::$criteriaEvent = $event;
    }
}
