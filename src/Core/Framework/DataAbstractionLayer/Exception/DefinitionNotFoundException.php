<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class DefinitionNotFoundException extends ShopwareHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'Definition for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DEFINITION_NOT_FOUND';
    }
}
