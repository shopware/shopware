<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
interface SalesChannelContextServiceInterface
{
    public function get(string $salesChannelId, string $token, ?string $languageId): SalesChannelContext;

    public function refresh(string $salesChannelId, string $token, ?string $languageId): void;
}
