<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

class MissingRequirementException extends RequirementException
{
    public function __construct(string $requirement, string $requiredVersion, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Required plugin/package "{{ requirement }} {{ version }}" is missing or not installed and activated',
            ['requirement' => $requirement, 'version' => $requiredVersion],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_REQUIREMENT_MISSING';
    }
}
