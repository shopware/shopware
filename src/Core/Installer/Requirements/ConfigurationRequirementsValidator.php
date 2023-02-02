<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Requirements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
#[Package('core')]
class ConfigurationRequirementsValidator implements RequirementsValidatorInterface
{
    private const MAX_EXECUTION_TIME_REQUIREMENT = 30;
    private const MEMORY_LIMIT_REQUIREMENT = '512M';
    private const OPCACHE_MEMORY_RECOMMENDATION = '256M';

    public function __construct(private readonly IniConfigReader $iniConfigReader)
    {
    }

    public function validateRequirements(RequirementsCheckCollection $checks): RequirementsCheckCollection
    {
        $checks->add($this->checkMaxExecutionTime());
        $checks->add($this->checkMemoryLimit());
        $checks->add($this->checkOpCache());

        return $checks;
    }

    private function checkMaxExecutionTime(): SystemCheck
    {
        $configuredValue = (int) $this->iniConfigReader->get('max_execution_time');

        return new SystemCheck(
            'max_execution_time',
            ($configuredValue >= self::MAX_EXECUTION_TIME_REQUIREMENT || $configuredValue === 0) ? RequirementCheck::STATUS_SUCCESS : RequirementCheck::STATUS_ERROR,
            (string) self::MAX_EXECUTION_TIME_REQUIREMENT,
            (string) $configuredValue
        );
    }

    private function checkMemoryLimit(): SystemCheck
    {
        $configuredValue = $this->iniConfigReader->get('memory_limit');

        $status = RequirementCheck::STATUS_ERROR;
        if (MemorySizeCalculator::convertToBytes($configuredValue) >= MemorySizeCalculator::convertToBytes(self::MEMORY_LIMIT_REQUIREMENT)) {
            $status = RequirementCheck::STATUS_SUCCESS;
        }
        // -1 means unlimited memory
        if ($configuredValue === '-1') {
            $status = RequirementCheck::STATUS_SUCCESS;
        }

        return new SystemCheck(
            'memory_limit',
            $status,
            self::MEMORY_LIMIT_REQUIREMENT,
            $configuredValue
        );
    }

    private function checkOpCache(): SystemCheck
    {
        $configuredValue = $this->iniConfigReader->get('opcache.memory_consumption');

        if ($configuredValue === '') {
            $configuredValue = '0';
        }
        $configuredValue .= 'M';

        $status = RequirementCheck::STATUS_WARNING;
        if (MemorySizeCalculator::convertToBytes($configuredValue) >= MemorySizeCalculator::convertToBytes(self::OPCACHE_MEMORY_RECOMMENDATION)) {
            $status = RequirementCheck::STATUS_SUCCESS;
        }

        return new SystemCheck(
            'opcache.memory_consumption',
            $status,
            self::OPCACHE_MEMORY_RECOMMENDATION,
            $configuredValue
        );
    }
}
