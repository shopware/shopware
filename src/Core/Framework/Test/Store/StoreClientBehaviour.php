<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use GuzzleHttp\Handler\MockHandler;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopware\Core\System\User\UserCollection;

trait StoreClientBehaviour
{
    public function getRequestHandler(): MockHandler
    {
        /** @var MockHandler $handler */
        $handler = $this->getContainer()->get('shopware.store.mock_handler');

        return $handler;
    }

    /**
     * @after
     * @before
     */
    public function resetStoreMock(): void
    {
        $this->getRequestHandler()->reset();
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

        return Context::createDefaultContext(new AdminApiSource($userId));
    }

    protected function getStoreTokenFromContext(Context $context): string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();

        $userId = $source->getUserId();

        if ($userId === null) {
            throw new \RuntimeException('No user id found in context');
        }

        /** @var UserCollection $user */
        $user = $this->getUserRepository()->search(new Criteria([$userId]), $context)->getEntities();

        if ($user->count() === 0) {
            throw new \RuntimeException('No user found with id ' . $userId);
        }

        return $user->first()->getStoreToken();
    }

    protected function getFrwUserTokenFromContext(Context $context): ?string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('userId', $source->getUserId()),
            new EqualsFilter('key', FirstRunWizardClient::USER_CONFIG_KEY_FRW_USER_TOKEN),
        );

        /** @var UserConfigEntity|null $config */
        $config = $this->getContainer()->get('user_config.repository')->search($criteria, $context)->first();

        return $config ? $config->getValue()[FirstRunWizardClient::USER_CONFIG_VALUE_FRW_USER_TOKEN] ?? null : null;
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
                'key' => FirstRunWizardClient::USER_CONFIG_KEY_FRW_USER_TOKEN,
                'value' => [
                    FirstRunWizardClient::USER_CONFIG_VALUE_FRW_USER_TOKEN => $frwUserToken,
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
