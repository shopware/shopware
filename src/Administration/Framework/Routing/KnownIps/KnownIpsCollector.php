<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Routing\KnownIps;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('administration')]
class KnownIpsCollector implements KnownIpsCollectorInterface
{
    /**
     * The result is mapped as ip => name|snippet-key. So by default it will look like this:
     * <code>
     *     [
     *         '127.0.0.1' => 'global.sw-multi-tag-ip-select.knownIps.you'
     *     ]
     * </code>
     */
    public function collectIps(Request $request): array
    {
        $result = [];

        if ($request->getClientIp()) {
            $result[$request->getClientIp()] = 'global.sw-multi-tag-ip-select.knownIps.you';
        }

        return $result;
    }
}
