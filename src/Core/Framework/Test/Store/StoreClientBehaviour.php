<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use GuzzleHttp\Handler\MockHandler;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;

trait StoreClientBehaviour
{
    public function getRequestHandler(): MockHandler
    {
        return $this->getContainer()->get('shopware.store.mock_handler');
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

        return $this->getUserRepository()->search(new Criteria([$source->getUserId()]), $context)
            ->first()->getStoreToken();
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

    protected function getUserRepository(): EntityRepositoryInterface
    {
        return $this->getContainer()->get('user.repository');
    }
}
