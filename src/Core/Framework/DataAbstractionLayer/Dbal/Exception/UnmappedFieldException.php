<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class UnmappedFieldException extends ShopwareHttpException
{
    public function __construct(
        string $field,
        EntityDefinition $definition
    ) {
        $fieldParts = explode('.', $field);
        $name = array_pop($fieldParts);

        parent::__construct(
            'Field "{{ field }}" in entity "{{ entity }}" was not found.',
            ['field' => $name, 'entity' => $definition->getEntityName()]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__UNMAPPED_FIELD';
    }
}
