<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('sales-channel')]
class SalesChannelRepositoryNotFoundException extends ShopwareHttpException
{
    public function __construct(string $entity)
    {
        parent::__construct(
            'SalesChannelRepository for entity "{{ entityName }}" does not exist.',
            ['entityName' => $entity]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__SALES_CHANNEL_REPOSITORY_NOT_FOUND';
    }
}
