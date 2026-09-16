<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AnnotationTagTester
{
    /**
     * captures any shopware version like 6.4.0.0 but also old version with 3 digits like 6.2.0
     */
    private const PLATFORM_VERSION_SCHEMA = '(\d+\.?){2,3}\d+';

    /**
     * captures a deprecation tag version like 6.5.0
     */
    private const PLATFORM_DEPRECATION_SCHEMA = 'v((\d+\.?){2}\d+)';

    /**
     * captures a manifest schema version like 1.0
     */
    private const MANIFEST_VERSION_SCHEMA = '(\d+\.\d+)';

    /**
     * short names of all BC-change attributes, see Shopware\Core\Framework\Deprecation\BCChange
     */
    private const BC_CHANGE_ATTRIBUTES = 'ReturnTypeNarrowing|ReturnTypeWidening|ParameterTypeNarrowing|ParameterTypeWidening|PropertyTypeNarrowing|PropertyTypeWidening|ExceptionChange|NewOptionalParameter|NewRequiredParameter|ParameterDefaultValueChange|ParameterNameChange|ParameterRemoval|BecomesAbstract|BecomesInternal|BecomesFinal|BecomesReadonly|ClassHierarchyChange|VisibilityChange';

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
        $pattern = \sprintf('/^v(%s)$/', self::PLATFORM_VERSION_SCHEMA);
        preg_match($pattern, $gitTag, $matches);

        return $matches[1] ?? null;
    }

    public static function getVersionFromManifestFileName(string $fileName): ?string
    {
        $matches = [];
        $pattern = \sprintf('/^manifest-%s.xsd/', self::MANIFEST_VERSION_SCHEMA);
        preg_match($pattern, $fileName, $matches);

        return $matches[1] ?? null;
    }

    public function validateDeprecatedAnnotations(string $content): void
    {
        /*
         * captures the first word after the @deprecated annotation
         */
        $annotationPattern = '/@deprecated(.*)?/';
        $matches = [];
        if (preg_match_all($annotationPattern, $content, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL)) {
            $this->validateMatches($matches, $this->validateDeprecationVersion(...));
        }
    }

    public function validateExperimentalAnnotations(string $content): void
    {
        /*
         * captures the first word after the @experimental annotation
         */
        $annotationPattern = '/@experimental(.*)/';
        $matches = [];
        if (preg_match_all($annotationPattern, $content, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL)) {
            $this->validateMatches($matches, $this->validateExperimentalVersion(...));
        }
    }

    /**
     * Validates the version argument of BC-change attribute usages, e.g.
     * `#[ReturnTypeNarrowing(version: 'v6.8.0', ...)]`. Fails when the version is
     * malformed or already released, so stale attributes are cleaned up at majors.
     */
    public function validateBCChangeAttributeVersions(string $content): void
    {
        $pattern = \sprintf('/#\[(?:%s)\(\s*(?:version:\s*)?\'([^\']*)\'/', self::BC_CHANGE_ATTRIBUTES);
        $matches = [];
        if (preg_match_all($pattern, $content, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->validateAgainstPlatformVersion($match[1]);
            }
        }
    }

    public function validateDeprecationElements(string $content): void
    {
        /*
         * captures everything between opening and closing </deprecated> element
         */
        $elementPattern = \sprintf(
            '/%s\s?(.*)\s?%s/',
            preg_quote('<deprecated>', '/'),
            preg_quote('</deprecated>', '/')
        );

        $matches = [];
        if (!preg_match_all($elementPattern, $content, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL)) {
            throw new \InvalidArgumentException('Deprecation tag is not found in the file.');
        }

        $this->validateMatches($matches, $this->validateDeprecationVersion(...));
    }

    /**
     * @param list<array<string|null>> $matches
     * @param callable(string):void $validateFunction
     */
    private function validateMatches(array $matches, callable $validateFunction): void
    {
        foreach ($matches as $match) {
            $validateFunction(trim($match[1] ?? ''));
        }
    }

    private function validateDeprecationVersion(string $versionTag): void
    {
        $match = [];
        preg_match('/(tag|manifest):([^\s]*)\s?/', $versionTag, $match, \PREG_UNMATCHED_AS_NULL);

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

        throw new \InvalidArgumentException('Could not find indicator manifest or tag in deprecation annotation.');
    }

    private function validateExperimentalVersion(string $propertiesString): void
    {
        $matches = [];
        preg_match_all('/([^\s:]+):([^\s]*)/', $propertiesString, $matches, \PREG_SET_ORDER);

        $properties = [];
        foreach ($matches as $match) {
            $properties[$match[1]] = $match[2];
        }

        if ($properties === []) {
            throw new \InvalidArgumentException('Incorrect format for experimental annotation. Properties `stableVersion` and/or `feature` are not declared.');
        }

        $unknownProperties = array_diff(array_keys($properties), ['stableVersion', 'feature']);
        if ($unknownProperties !== []) {
            throw new \InvalidArgumentException(\sprintf(
                'Unknown propert%s %s in experimental annotation. Only `stableVersion` and `feature` are allowed.',
                \count($unknownProperties) === 1 ? 'y' : 'ies',
                implode(', ', $unknownProperties)
            ));
        }

        // `stableVersion` is required; `feature` is optional (it only applies to flag-gated
        // experimental code) but must be in ALL_CAPS format when present.
        match (true) {
            !isset($properties['stableVersion']) => throw new \InvalidArgumentException('Could not find property stableVersion in experimental annotation.'),
            isset($properties['feature']) && !preg_match('/^(?:[A-Z]+(_[A-Z]+)*)+$/', $properties['feature']) => throw new \InvalidArgumentException('The value of feature-property can not be empty, contain white spaces and must be in ALL_CAPS format.'),
            default => $this->validateAgainstPlatformVersion($properties['stableVersion']),
        };
    }

    private function validateAgainstPlatformVersion(string $version): void
    {
        $pattern = \sprintf('/^%s$/', self::PLATFORM_DEPRECATION_SCHEMA);
        $matches = [];
        if (!preg_match($pattern, $version, $matches)) {
            throw new \InvalidArgumentException('The tag version should start with `v` and comprise 3 digits separated by periods.');
        }

        $this->compareVersion($this->shopwareVersion, $matches[1]);
    }

    private function validateAgainstManifestVersion(string $version): void
    {
        $pattern = \sprintf('/^v%s$/', self::MANIFEST_VERSION_SCHEMA);
        $matches = [];
        if (!preg_match($pattern, $version, $matches)) {
            throw new \InvalidArgumentException('Manifest version must have 2 digits.');
        }

        $this->compareVersion($this->manifestVersion, $matches[1]);
    }

    private function compareVersion(string $highestVersion, string $deprecatedVersion): void
    {
        if (version_compare($highestVersion, $deprecatedVersion) >= 0) {
            throw new \InvalidArgumentException('The version you used for deprecation or experimental annotation is already live.');
        }
    }
}
