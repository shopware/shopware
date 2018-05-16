<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Framework\ORM\Write\Validation;

use Exception;
use Shopware\Framework\ShopwareException;
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

    /**
     * @param ConstraintViolationListInterface $violations
     * @param string                           $message
     * @param null                             $code
     * @param Exception|null                   $previous
     */
    public function __construct(
        ConstraintViolationListInterface $violations,
        $message,
        $code = null,
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

        parent::__construct($message . "\n\t" . implode("\n\t<br>", $readableViolationList), $code, $previous);

        $this->violations = $violations;
    }

    /**
     * @param string $fieldName
     * @param string $cause
     *
     * @return bool
     */
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

    /**
     * @return ConstraintViolationListInterface
     */
    public function getViolations()
    {
        return $this->violations;
    }
}
