<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class SystemCheck extends RequirementCheck
{
    public function __construct(
        string $name,
        string $status,
        private readonly string $requiredValue,
        private readonly string $installedValue
    ) {
        parent::__construct($name, $status);
    }

    public function getRequiredValue(): string
    {
        return $this->requiredValue;
    }

    public function getInstalledValue(): string
    {
        return $this->installedValue;
    }
}
