<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Shopware\Core\Framework\Log\Package;
use Psr\Http\Message\RequestInterface;

/**
 * @internal only for use by the app-system
 * @package core
 */
#[Package('core')]
interface AppHandshakeInterface
{
    public function assembleRequest(): RequestInterface;

    public function fetchAppProof(): string;
}
