<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum AnnotationTagVersionSchema: string
{
    /**
     * captures any shopware version like 6.4.0.0 but also old version with 3 digits like 6.2.0
     */
    case PLATFORM_VERSION_SCHEMA = '((\d+\.?){2,3}\d+)';

    /**
     * captures a deprecation tag version like 6.5.0
     */
    case PLATFORM_DEPRECATION_SCHEMA = '((\d+\.?){2}\d+)';

    /**
     * captures a major feature flag version like 6.8.0.0
     */
    case PLATFORM_MAJOR_SCHEMA = '((\d+\.){3}\d+)';

    /**
     * captures a manifest schema version like 1.0
     */
    case MANIFEST_VERSION_SCHEMA = '(\d+\.\d+)';

    public function pattern(): string
    {
        return \sprintf('/^v%s$/', $this->value);
    }

    public function invalidMessage(): string
    {
        return match ($this) {
            self::PLATFORM_VERSION_SCHEMA, self::PLATFORM_DEPRECATION_SCHEMA => 'The tag version should start with `v` and comprise 3 digits separated by periods.',
            self::PLATFORM_MAJOR_SCHEMA => 'The silentUntil marker must reference a major feature flag, starting with `v` and comprising 4 digits separated by periods.',
            self::MANIFEST_VERSION_SCHEMA => 'Manifest version must have 2 digits.',
        };
    }
}
