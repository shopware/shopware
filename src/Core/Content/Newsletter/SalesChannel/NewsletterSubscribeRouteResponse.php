<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{status: string, success: bool}>>
 */
#[Package('after-sales')]
class NewsletterSubscribeRouteResponse extends StoreApiResponse
{
    public function __construct(string $status)
    {
        parent::__construct(new ArrayStruct([
            'status' => $status,
            'success' => true,
        ], 'newsletter_subscribe'));
    }

    public function getStatus(): string
    {
        return $this->object->get('status');
    }
}
