<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

class VersionMismatchException extends RequirementException
{
    protected $code = 'PLUGIN-REQUIREMENT-MISMATCH';

    public function __construct(
        string $requirement,
        string $requiredVersion,
        string $actualVersion,
        int $code = 0,
        \Throwable $previous = null
    ) {
        $message = sprintf(
            'Required plugin/package "%s %s" does not match installed version %s',
            $requirement,
            $requiredVersion,
            $actualVersion
        );

        parent::__construct($message, $code, $previous);
    }
}
