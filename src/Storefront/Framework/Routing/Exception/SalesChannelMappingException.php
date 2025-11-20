<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class SalesChannelMappingException extends StorefrontFrameworkException
{
    public function __construct(string $url)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            'FRAMEWORK__INVALID_SALES_CHANNEL_MAPPING',
            'Unable to find a matching sales channel for the request: "{{url}}". Please make sure the domain mapping is correct.',
            ['url' => $url]
        );
    }
}
