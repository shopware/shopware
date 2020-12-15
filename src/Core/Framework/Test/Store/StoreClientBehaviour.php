<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use GuzzleHttp\Handler\MockHandler;

trait StoreClientBehaviour
{
    public function getRequestHandler(): MockHandler
    {
        return $this->getContainer()->get('shopware.store.mock_handler');
    }
}
