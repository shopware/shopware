<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractManifestValidator
{
    abstract public function validate(Manifest $manifest, Context $context): ErrorCollection;
}
