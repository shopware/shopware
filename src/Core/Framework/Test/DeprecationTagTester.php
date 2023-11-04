<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class DeprecationTagTester
{
    /**
     * captures any shopware version like 6.4.0.0 but also old version with 3 digits like 6.2.0
     */
    private const PLATFORM_VERSION_SCHEMA = '(\d+\.?){2,3}\d+';

    /**
     * captures a deprecation tag version like 6.5.0
     */
    private const PLATFORM_DEPRECATION_SCHEMA = '(\d+\.?){2}\d+';

    /**
     * captures a manifest schema version like 1.0
     */
    private const MANIFEST_VERSION_SCHEMA = '\d+\.\d+';

    public function __construct(
        private readonly string $shopwareVersion,
        private readonly string $manifestVersion
    ) {
    }

    /**
     * This will capture any version number. For example:
     *     - v6.4.0.0 -> 6.4.0.0
     *     - v6.4.0.0 -> 6.4.0
     * But not malformed versions or single digits like
     *     - v1..1.1 -> null
     *     - v6.4.* -> null
     *     - v2 -> null
     *     - v2.2 -> null
     *     - 6.0.0.0 -> null
     *     - 6.4.0.0-RC-1 -> null
     */
    public static function getPlatformVersionFromGitTag(string $gitTag): ?string
    {
        $matches = [];
        $pattern = sprintf('/^v(%s)$/', self::PLATFORM_VERSION_SCHEMA);
        preg_match($pattern, $gitTag, $matches);

        return $matches[1] ?? null;
    }

    public static function getVersionFromManifestFileName(string $fileName): ?string
    {
        $matches = [];
        $pattern = sprintf('/^manifest-(%s).xsd/', self::MANIFEST_VERSION_SCHEMA);
        preg_match($pattern, $fileName, $matches);

        return $matches[1] ?? null;
    }

    public function validateAnnotations(string $content): void
    {
        /*
         * captures the first word after the @deprecated annotation
         */
        $annotationPattern = '/@deprecated\s*([^\s]*)\s?/';
        $matches = [];
        preg_match_all($annotationPattern, $content, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        $this->validateMatches($matches);
    }

    public function validateDeprecationElements(string $content): void
    {
        /*
         * captures everything between opening and closing </deprecated> element
         */
        $elementPattern = sprintf(
            '/%s\s?(.*)\s?%s/',
            preg_quote('<deprecated>', '/'),
            preg_quote('</deprecated>', '/')
        );

        $matches = [];
        preg_match_all($elementPattern, $content, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        $this->validateMatches($matches);
    }

    /**
     * @param array{1: string|null}[] $matches
     */
    private function validateMatches(array $matches): void
    {
        if (empty($matches)) {
            throw new NoDeprecationFoundException();
        }

        foreach ($matches as $match) {
            $this->validateVersion($match[1] ?? '');
        }
    }

    private function validateVersion(string $versionTag): void
    {
        $match = [];
        preg_match('/(tag|manifest):v(.*)/', $versionTag, $match, \PREG_UNMATCHED_AS_NULL);

        $tag = $match[1] ?? '';
        $version = $match[2] ?? '';

        if ($tag === 'tag') {
            $this->validateAgainstPlatformVersion($version);

            return;
        }

        if ($tag === 'manifest') {
            $this->validateAgainstManifestVersion($version);

            return;
        }

        throw new \InvalidArgumentException('Could not find indicator manifest or tag in deprecation');
    }

    private function validateAgainstPlatformVersion(string $version): void
    {
        $pattern = sprintf('/^%s$/', self::PLATFORM_DEPRECATION_SCHEMA);

        if (!preg_match($pattern, $version)) {
            throw new \InvalidArgumentException('Tag version must have 3 digits.');
        }

        $this->compareVersion($this->shopwareVersion, $version);
    }

    private function validateAgainstManifestVersion(string $version): void
    {
        $pattern = sprintf('/^%s$/', self::MANIFEST_VERSION_SCHEMA);

        if (!preg_match($pattern, $version)) {
            throw new \InvalidArgumentException('Manifest version must have 2 digits.');
        }

        $this->compareVersion($this->manifestVersion, $version);
    }

    private function compareVersion(string $highestVersion, string $deprecatedVersion): void
    {
        if (version_compare($highestVersion, $deprecatedVersion) >= 0) {
            throw new \InvalidArgumentException('The version you used for deprecation is already live.');
        }
    }
}
