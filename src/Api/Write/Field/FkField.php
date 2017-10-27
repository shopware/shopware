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

namespace Shopware\Api\Write\Field;

use Shopware\Api\Write\FieldAware\PathAware;
use Shopware\Api\Write\FieldAware\ValidatorAware;
use Shopware\Api\Write\FieldAware\WriteContextAware;
use Shopware\Api\Write\FieldException\InvalidFieldException;
use Shopware\Api\Write\WriteContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FkField extends Field implements WriteContextAware, ValidatorAware, PathAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var string
     */
    private $foreignClassName;

    /**
     * @var string
     */
    private $foreignFieldName;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var string
     */
    private $path;

    /**
     * @param string $storageName
     * @param string $foreignClassName
     * @param string $foreignFieldName
     */
    public function __construct(string $storageName, string $foreignClassName, string $foreignFieldName)
    {
        $this->foreignClassName = $foreignClassName;
        $this->foreignFieldName = $foreignFieldName;
        $this->storageName = $storageName;
    }

    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!$value) {
            try {
                $value = $this->writeContext->get($this->foreignClassName, $this->foreignFieldName);
            } catch (\InvalidArgumentException $exception) {
                $this->validate($key, $value);
            }
        }

        yield $this->storageName => $value;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }

    /**
     * @param string $fieldName
     * @param $value
     *
     * @throws InvalidFieldException
     */
    private function validate(string $fieldName, $value): void
    {
        $violationList = new ConstraintViolationList();
        $violations = $this->validator->validate($value, [new NotBlank()]);

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $violationList->add(
                new ConstraintViolation(
                    $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $violation->getParameters(),
                    $violation->getRoot(),
                    $fieldName,
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    $violation->getCode(),
                    $violation->getConstraint(),
                    $violation->getCause()
                )
            );
        }

        if (count($violationList)) {
            throw new InvalidFieldException($this->path . '/' . $fieldName, $violationList);
        }
    }
}
