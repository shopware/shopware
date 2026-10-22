<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Authentication;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Names the principal behind an authenticated Admin API MCP request:
 * - `user-<userId>` for user-bound tokens and user access keys
 * - `integration-<accessKey>` for integration tokens and access-key headers
 * - `integration-<accessKey>-user-<userId>` when an integration acts for a user (`sw-app-user-id`)
 *
 * The value never contains the token id, so it stays the same when a client refreshes its token.
 * See decision 5 of `adr/2026-10-21-mcp-oauth-client-authentication.md`.
 */
#[Package('framework')]
class McpPrincipalResolver
{
    public function resolve(Request $request): ?string
    {
        $tokenUserId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);
        if (\is_string($tokenUserId) && $tokenUserId !== '') {
            return 'user-' . $tokenUserId;
        }

        $clientId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        if (!\is_string($clientId) || $clientId === '') {
            return null;
        }

        try {
            $origin = AccessKeyHelper::getOrigin($clientId);
        } catch (ApiException) {
            return null;
        }

        $sourceUserId = $this->sourceUserId($request);

        if ($origin === 'user') {
            return $sourceUserId !== null ? 'user-' . $sourceUserId : null;
        }

        if ($origin === 'integration') {
            return 'integration-' . $clientId . ($sourceUserId !== null ? '-user-' . $sourceUserId : '');
        }

        return null;
    }

    /**
     * The user the request acts for, as resolved by the Admin API context: the owner of a user access
     * key, or the validated `sw-app-user-id` of an integration request.
     */
    private function sourceUserId(Request $request): ?string
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            return null;
        }

        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }
}
