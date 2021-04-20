<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class EntityRepositoryNotFoundException extends ShopwareHttpException
{
    public function __construct(string $entity, ?\Throwable $previous = null)
    {
        parent::__construct(
            'EntityRepository for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__EntityRepository_NOT_FOUND';
    }
}
