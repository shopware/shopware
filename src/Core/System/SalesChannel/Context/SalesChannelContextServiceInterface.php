<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
interface SalesChannelContextServiceInterface
{
    /**
     * @deprecated tag:v6.4.0 - Parameter $currencyId will be mandatory in future implementation
     */
    public function get(string $salesChannelId, string $token, ?string $languageId /*, ?string $currencyId */): SalesChannelContext;
}
