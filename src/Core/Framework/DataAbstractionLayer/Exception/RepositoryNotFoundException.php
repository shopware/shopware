<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class RepositoryNotFoundException extends ShopwareHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct('Repository for entity "{{ entityName }}" does not exist.', ['entityName' => $entity]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__REPOSITORY_NOT_FOUND';
    }
}
