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

use Shopware\Api\Write\FieldAware\ConstraintBuilderAware;
use Shopware\Api\Write\FieldAware\PathAware;
use Shopware\Api\Write\FieldAware\ValidatorAware;
use Shopware\Api\Write\FieldAware\ValueTransformerRegistryAware;
use Shopware\Api\Write\FieldException\InvalidFieldException;
use Shopware\Api\Write\ValueTransformer\ValueTransformer;
use Shopware\Api\Write\ValueTransformer\ValueTransformerDate;
use Shopware\Api\Write\ValueTransformer\ValueTransformerRegistry;
use Shopware\Api\Write\WriteResource;
use Shopware\Framework\Validation\ConstraintBuilder;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DateField extends Field implements PathAware, ConstraintBuilderAware, ValueTransformerRegistryAware, ValidatorAware
{
    /**
     * @var ConstraintBuilder
     */
    private $constraintBuilder;

    /**
     * @var ValidatorInterface
     */
    private $validator;
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var string
     */
    private $path;

    /**
     * @var ValueTransformerRegistry
     */
    private $valueTransformerRegistry;

    /**
     * @param string $storageName
     */
    public function __construct(string $storageName)
    {
        $this->storageName = $storageName;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        switch ($type) {
            case WriteResource::FOR_INSERT:
                $this->validate($this->getInsertConstraints(), $key, $value);
                break;
            case WriteResource::FOR_UPDATE:
                $this->validate($this->getUpdateConstraints(), $key, $value);
                break;
            default:
                throw new \DomainException(sprintf('Could not understand %s', $type));
        }

        yield $this->storageName => $this->getValueTransformer()->transform($value);
    }

    /**
     * {@inheritdoc}
     */
    public function setConstraintBuilder(ConstraintBuilder $constraintBuilder): void
    {
        $this->constraintBuilder = $constraintBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function setValueTransformerRegistry(ValueTransformerRegistry $valueTransformerRegistry): void
    {
        $this->valueTransformerRegistry = $valueTransformerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }

    /**
     * @param array  $constraints
     * @param string $fieldName
     * @param $value
     */
    private function validate(array $constraints, string $fieldName, $value)
    {
        $violationList = new ConstraintViolationList();

        foreach ($constraints as $constraint) {
            $violations = $this->validator
                ->validate($value, $constraint);

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
        }

        if (count($violationList)) {
            throw new InvalidFieldException($this->path . '/' . $fieldName, $violationList);
        }
    }

    /**
     * @return array
     */
    private function getInsertConstraints(): array
    {
        return $this->constraintBuilder
            ->isNotBlank()
            ->isDate()
            ->getConstraints();
    }

    /**
     * @return array
     */
    private function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->isDate()
            ->getConstraints();
    }

    /**
     * @return ValueTransformer
     */
    private function getValueTransformer(): ValueTransformer
    {
        return $this->valueTransformerRegistry
            ->get(ValueTransformerDate::class);
    }
}
