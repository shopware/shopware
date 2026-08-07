<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class UnmappedFieldException extends DataAbstractionLayerException
{
    public function __construct(string $field, EntityDefinition $definition)
    {
        $fieldParts = explode('.', $field);
        $name = array_pop($fieldParts);

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            DataAbstractionLayerException::DBAL_UNMAPPED_FIELD,
            'Field "{{ field }}" in entity "{{ entity }}" was not found.',
            ['field' => $name, 'entity' => $definition->getEntityName()]
        );
    }
}
