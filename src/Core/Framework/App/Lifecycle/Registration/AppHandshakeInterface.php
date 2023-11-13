<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
interface AppHandshakeInterface
{
    public function assembleRequest(): RequestInterface;

    public function fetchAppProof(): string;
}
