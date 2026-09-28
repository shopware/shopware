<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Names the principal a stored tool result belongs to, derived from the authenticated request:
 * the integration and user on the Admin API, the sales channel and context token on the Store API.
 * A Store API context token changes on login and logout, so a result stored before that is no
 * longer readable afterwards.
 */
#[Package('framework')]
final class McpToolResultPrincipal
{
    public static function fromRequest(Request $request): ?string
    {
        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if ($salesChannelContext instanceof SalesChannelContext) {
            return 'store:' . $salesChannelContext->getSalesChannelId() . ':' . $salesChannelContext->getToken();
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return null;
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        $integrationId = $source->getIntegrationId();
        $userId = $source->getUserId();
        if ($integrationId === null && $userId === null) {
            return null;
        }

        return 'admin:' . ($integrationId ?? '') . ':' . ($userId ?? '');
    }
}
