<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use GuzzleHttp\Handler\MockHandler;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

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

    private function createAdminStoreContext(): Context
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

        $this->getContainer()->get('user.repository')->create($data, Context::createDefaultContext());

        return Context::createDefaultContext(new AdminApiSource($userId));
    }
}
