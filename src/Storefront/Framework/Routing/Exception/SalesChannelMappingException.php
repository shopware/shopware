<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('storefront')]
class SalesChannelMappingException extends ShopwareHttpException
{
    public function __construct(string $url)
    {
        parent::__construct(
            'Unable to find a matching sales channel for the request: {{url}}". Please make sure the domain mapping is correct.',
            ['url' => $url]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_SALES_CHANNEL_MAPPING';
    }
}
