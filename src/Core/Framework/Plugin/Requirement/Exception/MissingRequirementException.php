<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

class MissingRequirementException extends RequirementException
{
    protected $code = 'PLUGIN-REQUIREMENT-MISSING';

    public function __construct(string $requirement, string $requiredVersion, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Required plugin/package "%s %s" is missing', $requirement, $requiredVersion);

        parent::__construct($message, $code, $previous);
    }
}
