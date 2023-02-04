<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class NoEntityClonedException extends ShopwareHttpException
{
    public function __construct(
        string $entity,
        string $id
    ) {
        parent::__construct(
            'Could not clone entity {{ entity }} with id {{ id }}.',
            ['entity' => $entity, 'id' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__NO_ENTITIY_CLONED_ERROR';
    }
}
