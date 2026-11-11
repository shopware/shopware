<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Composer\Semver\VersionParser;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\App\Validation\Error\IncompatibleAppError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class CompatibilityValidator extends AbstractManifestValidator
{
    public function __construct(private readonly string $shopwareVersion)
    {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $versionParser = new VersionParser();
        if ($manifest->getMetadata()->getCompatibility()->matches($versionParser->parseConstraints($this->shopwareVersion))) {
            return [];
        }

        return [new IncompatibleAppError($manifest->getMetadata()->getName())];
    }
}
