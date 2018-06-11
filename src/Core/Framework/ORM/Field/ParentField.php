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

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\Flag\Inherited;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ParentField extends FkField
{
    /**
     * @var string
     */
    protected $referenceClass;

    /**
     * @var string
     */
    protected $referenceField;

    public function __construct(string $referenceClass)
    {
        parent::__construct('parent_id', 'parentId', $referenceClass, 'id');
        $this->referenceClass = $referenceClass;
    }

    public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if ($this->shouldUseContext($data, $existence)) {
            try {
                $value = $this->writeContext->get($this->referenceClass, $this->referenceField);
            } catch (\InvalidArgumentException $exception) {
                $this->validate($key, $value, $existence);
            }
        }

        if ($value === null) {
            yield $this->storageName => null;
            yield $this->tenantIdField => null;

            return;
        }

        yield $this->storageName => Uuid::fromStringToBytes($value);
        yield $this->tenantIdField => Uuid::fromStringToBytes($this->writeContext->getContext()->getTenantId());
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return 70;
    }

    /**
     * @param string $fieldName
     * @param $value
     * @param array $raw
     */
    private function validate(string $fieldName, $value, EntityExistence $existence): void
    {
        $violationList = new ConstraintViolationList();

        $constraints = $this->getConstraints($existence);

        $violations = $this->validator->validate($value, $constraints);

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

    private function getConstraints(EntityExistence $existence): array
    {
        if ($this->is(Inherited::class) && $existence->isChild()) {
            return [];
        }

        if ($this->is(Required::class)) {
            return [new NotBlank()];
        }

        return [];
    }

    /**
     * @param KeyValuePair    $data
     * @param EntityExistence $existence
     *
     * @return bool
     */
    private function shouldUseContext(KeyValuePair $data, EntityExistence $existence): bool
    {
        return $data->isRaw() && $data->getValue() === null && $this->is(Required::class);
    }
}
