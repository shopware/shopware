<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[Package('core')]
class VersionParser
{
    /**
     * @var string Regex pattern for validating Shopware versions
     */
    private const VALID_VERSION_PATTERN = '#^\d\.\d+\.\d+\.(\d+|x)(-\w+)?#';

    /**
     * @return array{version: string, revision: string}
     */
    public static function parseShopwareVersion(?string $version): array
    {
        // does not come from composer, was set manually
        if ($version === null || mb_strpos($version, '@') === false) {
            return [
                'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
                'revision' => str_repeat('0', 32),
            ];
        }

        [$version, $hash] = explode('@', $version);
        $version = ltrim($version, 'v');
        $version = str_replace('+', '-', $version);

        /*
         * checks if the version is a valid version pattern
         * \Shopware\Tests\Unit\Core\Framework\Util\VersionParserTest::testParseShopwareVersion
         */
        if (!preg_match(self::VALID_VERSION_PATTERN, $version)) {
            $version = Kernel::SHOPWARE_FALLBACK_VERSION;
        }

        return [
            'version' => $version,
            'revision' => $hash,
        ];
    }
}
