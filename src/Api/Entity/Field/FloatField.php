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

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\Write\FieldAware\ConstraintBuilderAware;
use Shopware\Api\Entity\Write\FieldAware\FilterRegistryAware;
use Shopware\Api\Entity\Write\FieldAware\PathAware;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\FieldAware\ValidatorAware;
use Shopware\Api\Entity\Write\FieldException\InvalidFieldException;
use Shopware\Api\Entity\Write\Filter\FilterRegistry;
use Shopware\Api\Entity\Write\Validation\ConstraintBuilder;
use Shopware\Api\Entity\Write\WriteResource;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FloatField extends Field implements PathAware, ConstraintBuilderAware, FilterRegistryAware, ValidatorAware, StorageAware
{
    /**
     * @var ConstraintBuilder
     */
    private $constraintBuilder;

    /**
     * @var FilterRegistry
     */
    private $filterRegistry;

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

    public function __construct(string $storageName, string $propertyName)
    {
        $this->storageName = $storageName;
        parent::__construct($propertyName);
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

        yield $this->storageName => $value;
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
    public function setFilterRegistry(FilterRegistry $filterRegistry): void
    {
        $this->filterRegistry = $filterRegistry;
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

    public function getStorageName(): string
    {
        return $this->storageName;
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
            ->isNumeric()
            ->getConstraints();
    }

    /**
     * @return array
     */
    private function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->isNumeric()
            ->getConstraints();
    }
}
