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

namespace Shopware\Core\Content\Catalog\ORM;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class CatalogField extends FkField
{
    public function __construct()
    {
        parent::__construct('catalog_id', 'catalogId', CatalogDefinition::class);

        $this->setFlags(new Required());
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(EntityExistence $existence, KeyValuePair $kvPair): \Generator
    {
        if ($this->writeContext->has($this->definition, 'catalogId')) {
            $value = $this->writeContext->get($this->definition, 'catalogId');
        } elseif (!empty($kvPair->getValue())) {
            $value = $kvPair->getValue();
        } else {
            $value = Defaults::CATALOG;
        }

        $restriction = $this->writeContext->getContext()->getCatalogIds();

        //user has restricted catalog access
        if (is_array($restriction)) {
            $this->validateCatalog($restriction, $value, $existence);
        }

        //write catalog id of current object to write context
        $this->writeContext->set($this->definition, 'catalogId', $value);
        if ($this->definition::getTranslationDefinitionClass()) {
            $this->writeContext->set($this->definition::getTranslationDefinitionClass(), 'catalogId', $value);
        }

        yield $this->storageName => Uuid::fromStringToBytes($value);
        yield 'catalog_tenant_id' => Uuid::fromStringToBytes($this->writeContext->getContext()->getTenantId());
    }

    public function getExtractPriority(): int
    {
        return 1000;
    }

    private function validateCatalog(array $restrictedCatalogs, $catalogId, EntityExistence $existence): void
    {
        $violationList = new ConstraintViolationList();
        $violations = $this->validator->validate($catalogId, [new Choice(['choices' => $restrictedCatalogs])]);

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $violationList->add(
                new ConstraintViolation(
                    sprintf('No access to catalog id: %s', $catalogId),
                    'No access to catalog id: {{ value }}',
                    $violation->getParameters(),
                    $violation->getRoot(),
                    'catalogId',
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    $violation->getCode(),
                    $violation->getConstraint(),
                    $violation->getCause()
                )
            );
        }

        if (count($violationList)) {
            throw new InvalidFieldException($this->path . '/catalogId', $violationList);
        }
    }
}
