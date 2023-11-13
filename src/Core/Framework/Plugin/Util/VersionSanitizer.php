<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class VersionSanitizer
{
    public function sanitizePluginVersion(string $version): string
    {
        // Matches ".0", ".123", etc
        $regex = '/(\.\d+)/';
        $counter = 1;

        return preg_replace_callback(
            $regex,
            static function ($match) use (&$counter) {
                // Third occurrence of a dot with following digits will be removed.
                // This is the fourth number of the version string, which is returned by Composer
                if ($counter === 3) {
                    return '';
                }

                ++$counter;

                return $match[0];
            },
            $version
        );
    }
}
