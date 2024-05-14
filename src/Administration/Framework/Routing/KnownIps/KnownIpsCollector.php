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
     *         '127.0.0.1' => 'global.sw-multi-tag-ip-select.knownIps.you',
     *         // or
     *         '2001:0db8:0123:4567:89ab:cdef:1234:5678' => 'global.sw-multi-tag-ip-select.knownIps.you',
     *         '2001:0db8:0123:4567::/64' => 'global.sw-multi-tag-ip-select.knownIps.youIPv6Block64',
     *         '2001:0db8:0123:4500::/56' => 'global.sw-multi-tag-ip-select.knownIps.youIPv6Block56'
     *     ]
     * </code>
     */
    public function collectIps(Request $request): array
    {
        $result = [];
        $clientIp = $request->getClientIp();

        if (!\is_string($clientIp)) {
            return $result;
        }

        $result[$clientIp] = 'global.sw-multi-tag-ip-select.knownIps.you';

        $isIpV6 = \str_contains($clientIp, ':') && \filter_var($clientIp, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6);
        if (!$isIpV6) {
            return $result;
        }

        if (!$this->isIPv6SupportAvailable()) {
            return $result;
        }

        $packedInAddrRepresentation = \inet_pton($clientIp);
        if (!\is_string($packedInAddrRepresentation)) {
            return $result;
        }

        $binaryRepresentation64 = \hex2bin(\substr(\bin2hex($packedInAddrRepresentation), 0, 16) . \str_repeat('0', 16));
        $binaryRepresentation56 = \hex2bin(\substr(\bin2hex($packedInAddrRepresentation), 0, 14) . \str_repeat('0', 18));

        // replace last half (and slightly more for /56)
        if (\is_string($binaryRepresentation64)) {
            $cidr64 = \inet_ntop($binaryRepresentation64) . '/64';
            $result[$cidr64] = 'global.sw-multi-tag-ip-select.knownIps.youIPv6Block64';
        }

        if (\is_string($binaryRepresentation56)) {
            $cidr56 = \inet_ntop($binaryRepresentation56) . '/56';
            $result[$cidr56] = 'global.sw-multi-tag-ip-select.knownIps.youIPv6Block56';
        }

        return $result;
    }

    private function isIPv6SupportAvailable(): bool
    {
        if (!((\extension_loaded('sockets') && \defined('AF_INET6')) || @\inet_pton('::1'))) {
            return false;
        }

        return true;
    }
}
