<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;

/**
 * @internal
 */
class StoreHandshake implements AppHandshakeInterface
{
    public function assembleRequest(): RequestInterface
    {
        throw new AppRegistrationException('Not implemented');
    }

    public function fetchAppProof(): string
    {
        throw new AppRegistrationException('Not implemented');
    }
}
