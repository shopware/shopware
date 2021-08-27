<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;

/**
 * @Decoratable
 */
class FileUrlValidator implements FileUrlValidatorInterface
{
    public function isValid(string $source): bool
    {
        $host = parse_url($source, \PHP_URL_HOST);
        if ($host === false || $host === null) {
            return false;
        }

        $ip = gethostbyname($host);

        // Potentially IPv6
        $ip = trim($ip, '[]');

        return $this->validateIp($ip);
    }

    private function validateIp(string $ip): bool
    {
        $ip = filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE
        );

        if (!$ip) {
            return false;
        }

        if (!filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return true;
        }

        // Convert IPv6 to packed format and back so we can check if there is a IPv4 representation of the IP
        $packedIp = inet_pton($ip);
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
            return $this->validateIp($ipv4);
        }

        return true;
    }
}
