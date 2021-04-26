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
        $ip = gethostbyname($host);

        // Potentially IPv6
        $ip = trim($ip, '[]');

        $ip = filter_var(
            $ip,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE
        );

        if (!$ip) {
            return false;
        }

        return true;
    }
}
