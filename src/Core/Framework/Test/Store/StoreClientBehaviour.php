<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use GuzzleHttp\Handler\MockHandler;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('merchant-services')]
trait StoreClientBehaviour
{
    /**
     * @deprecated tag:v6.6.0 - Will be removed, use ::getStoreRequestHandler() instead
     */
    public function getRequestHandler(): MockHandler
    {
        return $this->getStoreRequestHandler();
    }

    public function getStoreRequestHandler(): MockHandler
    {
        /** @var MockHandler $handler */
        $handler = $this->getContainer()->get('shopware.store.mock_handler');

        return $handler;
    }

    public function getFrwRequestHandler(): MockHandler
    {
        /** @var MockHandler $handler */
        $handler = $this->getContainer()->get('shopware.frw.mock_handler');

        return $handler;
    }

    /**
     * @after
     *
     * @before
     */
    public function resetStoreMock(): void
    {
        $this->getStoreRequestHandler()->reset();
    }

    /**
     * @after
     *
     * @before
     */
    public function resetFrwMock(): void
    {
        $this->getFrwRequestHandler()->reset();
    }

    protected function createAdminStoreContext(): Context
    {
        $userId = Uuid::randomHex();
        $storeToken = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => Uuid::randomHex() . '@bar.com',
                'storeToken' => $storeToken,
            ],
        ];

        $this->getUserRepository()->create($data, Context::createDefaultContext());

        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);

        return Context::createDefaultContext($source);
    }

    protected function getStoreTokenFromContext(Context $context): string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();

        $userId = $source->getUserId();

        if ($userId === null) {
            throw new \RuntimeException('No user id found in context');
        }

        /** @var UserCollection $users */
        $users = $this->getUserRepository()->search(new Criteria([$userId]), $context)->getEntities();

        if ($users->count() === 0) {
            throw new \RuntimeException('No user found with id ' . $userId);
        }

        $user = $users->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $token = $user->getStoreToken();
        static::assertIsString($token);

        return $token;
    }

    protected function getFrwUserTokenFromContext(Context $context): ?string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('userId', $source->getUserId()),
            new EqualsFilter('key', FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN),
        );

        /** @var UserConfigEntity|null $config */
        $config = $this->getContainer()->get('user_config.repository')->search($criteria, $context)->first();

        return $config ? $config->getValue()[FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN] ?? null : null;
    }

    protected function setFrwUserToken(Context $context, string $frwUserToken): void
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw new \RuntimeException('Context with AdminApiSource expected.');
        }

        $this->getContainer()->get('user_config.repository')->create([
            [
                'userId' => $source->getUserId(),
                'key' => FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN,
                'value' => [
                    FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN => $frwUserToken,
                ],
            ],
        ], Context::createDefaultContext());
    }

    protected function setLicenseDomain(?string $licenseDomain): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $systemConfigService->set(
            'core.store.licenseHost',
            $licenseDomain
        );
    }

    protected function setShopSecret(string $shopSecret): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $systemConfigService->set(
            'core.store.shopSecret',
            $shopSecret
        );
    }

    protected function getShopwareVersion(): string
    {
        $version = $this->getContainer()->getParameter('kernel.shopware_version');

        return $version === Kernel::SHOPWARE_FALLBACK_VERSION ? '___VERSION___' : $version;
    }

    protected function getUserRepository(): EntityRepository
    {
        return $this->getContainer()->get('user.repository');
    }
}
