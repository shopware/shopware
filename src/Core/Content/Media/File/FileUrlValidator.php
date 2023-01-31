<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class FileUrlValidator implements FileUrlValidatorInterface
{
    public function isValid(string $source): bool
    {
        $host = parse_url($source, \PHP_URL_HOST);
        if ($host === false || $host === null) {
            return false;
        }

        $ip = gethostbyname($host);

        if (str_contains($ip, '[')) {
            return $this->validateIpv6(trim($ip, '[]'));
        }

        return $this->validateIpv4($ip);
    }

    private function validateIpv4(string $ip): bool
    {
        $ipv4 = filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE | \FILTER_FLAG_IPV4
        );

        return $ipv4 !== false;
    }

    private function validateIpv6(string $ip): bool
    {
        $ipv6 = filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE | \FILTER_FLAG_IPV6
        );

        if (!$ipv6) {
            return false;
        }

        // Convert IPv6 to packed format and back so we can check if there is a IPv4 representation of the IP
        $packedIp = inet_pton($ipv6);
        if (!$packedIp) {
            return false;
        }

        $convertedIp = inet_ntop($packedIp);
        if (!$convertedIp) {
            return false;
        }
        $convertedIp = explode(':', $convertedIp);
        $ipv4 = array_pop($convertedIp);

        // Additionally filter IPv4 representation of the IP
        if (filter_var($ipv4, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return $this->validateIpv4($ipv4);
        }

        return true;
    }
}
