<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements;

use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;

/**
 * @package core
 *
 * @internal
 */
interface RequirementsValidatorInterface
{
    public function validateRequirements(RequirementsCheckCollection $checks): RequirementsCheckCollection;
}
