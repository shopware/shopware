<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Composer\Semver\VersionParser;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
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

    public function validate(Manifest $manifest, ?Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $versionParser = new VersionParser();
        if (!$manifest->getMetadata()->getCompatibility()->matches($versionParser->parseConstraints($this->shopwareVersion))) {
            $errors->add(new IncompatibleAppError($manifest->getMetadata()->getName()));
        }

        return $errors;
    }
}
