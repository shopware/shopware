<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ContactFormRouteResponseStruct>
 */
#[Package('discovery')]
class ContactFormRouteResponse extends StoreApiResponse
{
    public function getResult(): ContactFormRouteResponseStruct
    {
        return $this->object;
    }
}
