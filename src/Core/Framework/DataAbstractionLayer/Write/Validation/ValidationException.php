<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Exception;
use Shopware\Core\Framework\ShopwareException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends \InvalidArgumentException implements ShopwareException
{
    /**
     * @var ConstraintViolationListInterface
     */
    private $violations;

    /**
     * @var array
     */
    private $sortedViolations = [];

    public function __construct(
        ConstraintViolationListInterface $violations,
        string $message,
        int $code = null,
        Exception $previous = null
    ) {
        $readableViolationList = [];

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $fieldName = $violation->getPropertyPath();

            if (!isset($this->sortedViolations[$fieldName])) {
                $this->sortedViolations[$fieldName] = [];
            }

            $this->sortedViolations[$fieldName][] = $violation;
            $readableViolationList[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        $message .= "\n\t" . implode("\n\t<br>", $readableViolationList);

        parent::__construct($message, $code, $previous);

        $this->violations = $violations;
    }

    public function hasViolationsForFieldWithCause(string $fieldName, string $cause): bool
    {
        if (!isset($this->sortedViolations[$fieldName])) {
            return false;
        }

        /** @var ConstraintViolation $violation */
        foreach ($this->sortedViolations[$fieldName] as $violation) {
            if ($violation->getCause() === $cause) {
                return true;
            }
        }

        return false;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
