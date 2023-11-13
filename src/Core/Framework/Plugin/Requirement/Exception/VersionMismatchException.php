<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class VersionMismatchException extends RequirementException
{
    public function __construct(
        string $requirement,
        string $requiredVersion,
        string $actualVersion
    ) {
        parent::__construct(
            'Required plugin/package "{{ requirement }} {{ requiredVersion }}" does not match installed version {{ version }}.',
            [
                'requirement' => $requirement,
                'requiredVersion' => $requiredVersion,
                'version' => $actualVersion,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_REQUIREMENT_MISMATCH';
    }
}
