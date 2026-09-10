<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Error;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Validation\Requirements\UnmetRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class UnmetRequirementError implements Error
{
    private readonly string $violations;

    private readonly string $message;

    public function __construct(UnmetRequirement ...$violations)
    {
        $violationDetails = array_map(
            fn (UnmetRequirement $violation) => \sprintf(
                'App "%s" - Requirement "%s": %s',
                $violation->appName,
                $violation->requirementName,
                $violation->actionableResolution
            ),
            $violations
        );

        $this->violations = implode('; ', $violationDetails);

        $this->message = \sprintf('The app requirements are not met: %s', $this->violations);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::APP_REQUIREMENTS_NOT_MET;
    }

    public function getParameters(): array
    {
        return ['violations' => $this->violations];
    }

    public function isBlocking(): bool
    {
        return false;
    }
}
