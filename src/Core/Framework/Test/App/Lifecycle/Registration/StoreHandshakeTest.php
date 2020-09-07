<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle\Registration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Registration\StoreHandshake;

class StoreHandshakeTest extends TestCase
{
    public function testGetHandshakeAssembleRequestIsUnimplemented(): void
    {
        $storeHandshake = new StoreHandshake();
        static::expectException(\RuntimeException::class);
        $storeHandshake->assembleRequest();
    }

    public function testGetHandshakeFetchAppProofIsUnimplemented(): void
    {
        $storeHandshake = new StoreHandshake();
        static::expectException(\RuntimeException::class);
        $storeHandshake->fetchAppProof();
    }
}
