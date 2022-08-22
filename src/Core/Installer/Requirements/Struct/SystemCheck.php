<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements\Struct;

/**
 * @internal
 */
class SystemCheck extends RequirementCheck
{
    private string $requiredValue;

    private string $installedValue;

    public function __construct(string $name, string $status, string $requiredValue, string $installedValue)
    {
        parent::__construct($name, $status);

        $this->requiredValue = $requiredValue;
        $this->installedValue = $installedValue;
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
