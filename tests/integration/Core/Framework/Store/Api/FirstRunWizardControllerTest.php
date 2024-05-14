<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Api;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Api\FirstRunWizardController;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Store\Event\FirstRunWizardStartedEvent;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
class FirstRunWizardControllerTest extends TestCase
{
    use EventDispatcherBehaviour;
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private FirstRunWizardController $frwController;

    protected function setUp(): void
    {
        $this->frwController = $this->getContainer()->get(FirstRunWizardController::class);
    }

    public function testFrwStartFiresTrackingEventAndDispatchesStartedEvent(): void
    {
        $dispatchedEvent = null;

        // Response for request of TrackingEventClient::fireTrackingEvent()
        $this->getStoreRequestHandler()->append(new Response());

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            FirstRunWizardStartedEvent::class,
            function (FirstRunWizardStartedEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;
            }
        );

        $this->frwController->frwStart(Context::createDefaultContext());

        static::assertInstanceOf(FirstRunWizardStartedEvent::class, $dispatchedEvent);

        $lastRequest = $this->getStoreRequestHandler()->getLastRequest();
        static::assertInstanceOf(Request::class, $lastRequest);
        static::assertEquals('POST', $lastRequest->getMethod());
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
    }

    public function testFrwLoginStoresFrwUserToken(): void
    {
        $frwUserToken = 'frw-us3r-t0k3n';
        $expirationDate = new \DateTimeImmutable('2022-12-15');
        $context = $this->createAdminStoreContext();

        // Response for request of FirstRunWizardClient::frwLogin()
        $this->getFrwRequestHandler()->append(new Response(
            body: json_encode([
                'firstRunWizardUserToken' => [
                    'token' => $frwUserToken,
                    'expirationDate' => $expirationDate->format(Defaults::STORAGE_DATE_FORMAT),
                ],
            ], \JSON_THROW_ON_ERROR),
        ));

        $this->frwController->frwLogin(
            new RequestDataBag([
                'shopwareId' => 'shopware-id',
                'password' => 'p4ssw0rd',
            ]),
            $context
        );

        static::assertEquals(
            $frwUserToken,
            $this->fetchUserConfig(FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN, FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN)
        );
    }

    public function testUpgradesFrwTokenToStoreTokenOnSuccessfulFrwFinish(): void
    {
        $dispatchedEvent = null;
        $shopUserToken = 'sh0p-us3r-t0k3n';
        $shopSecret = 'sh0p-s3cr3t';
        $context = $this->createAdminStoreContext();

        $this->setFrwUserToken($context, 'frw-us3r-t0k3n');

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            FirstRunWizardFinishedEvent::class,
            function (FirstRunWizardFinishedEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;
                $event->stopPropagation();
            },
            99999,
        );

        // Response for request of TrackEventClient::fireTrackingEvent()
        $this->getStoreRequestHandler()->append(new Response());

        // Response for request of FirstRunWizardClient::upgradeAccessToken()
        $this->getFrwRequestHandler()->append(new Response(
            body: json_encode([
                'shopUserToken' => [
                    'token' => $shopUserToken,
                    'expirationDate' => (new \DateTimeImmutable('2022-12-15'))->format(Defaults::STORAGE_DATE_FORMAT),
                ],
                'shopSecret' => $shopSecret,
            ], \JSON_THROW_ON_ERROR),
        ));

        $this->frwController->frwFinish(
            new QueryDataBag([
                'failed' => false,
            ]),
            $context
        );

        static::assertInstanceOf(FirstRunWizardFinishedEvent::class, $dispatchedEvent);
        static::assertNull(
            $this->fetchUserConfig(FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN, FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN)
        );
        static::assertEquals(
            $shopUserToken,
            $this->fetchStoreToken($context)
        );
        static::assertEquals(
            $shopSecret,
            $this->getContainer()->get(SystemConfigService::class)->getString(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET)
        );
    }

    public function testSetsLicenseHostAndShopSecretONSuccessfulDomainVerification(): void
    {
        $context = $this->createAdminStoreContext();

        // Response for first request of FirstRunWizardClient::getLicenseDomains()
        $this->getFrwRequestHandler()->append(new Response(
            body: json_encode([], \JSON_THROW_ON_ERROR),
        ));

        // Response for request of FirstRunWizardClient::fetchVerificationInfo()
        $this->getFrwRequestHandler()->append(new Response(
            body: json_encode([
                'fileName' => 'sw-verification-hash.html',
                'content' => 'sw-v3rific4ti0n-h4sh',
            ], \JSON_THROW_ON_ERROR),
        ));

        // Response for request of FirstRunWizardClient::checkVerificationSecret()
        $this->getFrwRequestHandler()->append(new Response());

        // Response for second request of FirstRunWizardClient::getLicenseDomains()
        $this->getFrwRequestHandler()->append(new Response(
            body: json_encode([
                [
                    'id' => 123456,
                    'domain' => 'shopware.swag',
                    'verified' => true,
                    'edition' => [
                        'name' => 'Community Edition',
                        'label' => 'Community Edition',
                    ],
                ],
            ], \JSON_THROW_ON_ERROR),
        ));

        $this->frwController->verifyDomain(
            new QueryDataBag([
                'domain' => 'shopware.swag',
                'testEnvironment' => true,
            ]),
            $context,
        );

        static::assertEquals(
            'shopware.swag',
            $this->getContainer()->get(SystemConfigService::class)->getString(StoreRequestOptionsProvider::CONFIG_KEY_STORE_LICENSE_DOMAIN)
        );
    }

    private function fetchUserConfig(string $configKey, string $valueKey): ?string
    {
        $value = $this->getContainer()->get(Connection::class)->executeQuery(
            'SELECT value FROM user_config WHERE `key` = :key',
            ['key' => $configKey]
        )->fetchOne();

        return $value ? json_decode($value, true, flags: \JSON_THROW_ON_ERROR)[$valueKey] : null;
    }

    private function fetchStoreToken(Context $context): ?string
    {
        $source = $context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);

        $userId = $source->getUserId();
        static::assertIsString($userId);

        $storeToken = $this->getContainer()->get(Connection::class)->executeQuery(
            'SELECT store_token FROM user WHERE `id` = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        )->fetchOne();

        return $storeToken ?: null;
    }
}
