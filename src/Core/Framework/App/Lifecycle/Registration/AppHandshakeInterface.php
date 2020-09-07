<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
interface AppHandshakeInterface
{
    public function assembleRequest(): RequestInterface;

    public function fetchAppProof(): string;
}
