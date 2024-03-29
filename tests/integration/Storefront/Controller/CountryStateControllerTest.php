<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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
#[Package('buyers-experience')]
class CountryStateControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private Connection $connection;

    private string $countryIdDE;

    private CountryStateController $countryStateController;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
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

        static::assertCount(16, \json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['states']);
    }

    public function testEmptyCountryId(): void
    {
        static::expectException(RoutingException::class);
        static::expectExceptionMessage('Parameter "countryId" is missing.');
        $this->countryStateController->getCountryData(new Request([], ['countryId' => null]), $this->salesChannelContext);
    }

    public function testCountryStateControllerEvents(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $testSubscriber = new CountryStateControllerTestSubscriber();
        $dispatcher->addSubscriber($testSubscriber);

        $this->countryStateController->getCountryData(new Request([], ['countryId' => $this->countryIdDE]), $this->salesChannelContext);

        $dispatcher->removeSubscriber($testSubscriber);

        static::assertInstanceOf(CountryStateDataPagelet::class, $testSubscriber->testPagelet);
        static::assertInstanceOf(CountryStateDataPageletCriteriaEvent::class, $testSubscriber->criteriaEvent);
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
                    'hook' => 'country-state-data-pagelet-loaded',
                    'script' => '{% do debug.dump(hook.getPage.getStates.count) %}',
                    'active' => true,
                ],
            ],
        ]], Context::createDefaultContext());

        $request = new Request([], ['countryId' => $this->countryIdDE]);
        $this->getContainer()->get('request_stack')->push($request);

        $this->countryStateController->getCountryData($request, $this->salesChannelContext);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CountryStateDataPageletLoadedHook::HOOK_NAME, $traces);

        static::assertEquals(['16'], $traces['country-state-data-pagelet-loaded'][0]['output']);
    }
}

/**
 * @internal
 */
class CountryStateControllerTestSubscriber implements EventSubscriberInterface
{
    public ?CountryStateDataPagelet $testPagelet = null;

    public ?CountryStateDataPageletCriteriaEvent $criteriaEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            CountryStateDataPageletLoadedEvent::class => 'onPageletLoaded',
            CountryStateDataPageletCriteriaEvent::class => 'onCriteria',
        ];
    }

    public function onPageletLoaded(CountryStateDataPageletLoadedEvent $event): void
    {
        $this->testPagelet = $event->getPagelet();
    }

    public function onCriteria(CountryStateDataPageletCriteriaEvent $event): void
    {
        $this->criteriaEvent = $event;
    }
}
