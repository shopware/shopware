<?php declare(strict_types=1);

namespace Shopware\Core\Framework\RateLimiter;

use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class RateLimiter
{
    final public const LOGIN_ROUTE = 'login';

    final public const LOGIN_USER = 'login_user';

    final public const LOGIN_CLIENT = 'login_client';

    final public const GUEST_LOGIN = 'guest_login';

    final public const RESET_PASSWORD = 'reset_password';

    final public const OAUTH = 'oauth';

    final public const OAUTH_USER = 'oauth_user';

    final public const OAUTH_CLIENT = 'oauth_client';

    final public const USER_RECOVERY = 'user_recovery';

    final public const CONTACT_FORM = 'contact_form';

    final public const NEWSLETTER_FORM = 'newsletter_form';

    final public const NEWSLETTER_UNSUBSCRIBE_FORM = 'newsletter_unsubscribe_form';

    final public const REVOCATION_REQUEST_FORM = 'revocation_request_form';

    final public const CART_ADD_LINE_ITEM = 'cart_add_line_item';

    final public const MCP_ADMIN_API = 'mcp_admin_api';

    final public const MCP_STORE_API = 'mcp_store_api';

    final public const APP_SHOP_VERIFY = 'app_shop_verify';

    /**
     * @var array<string, RateLimiterFactory>
     */
    private array $factories;

    public function reset(string $route, string $key): void
    {
        $this->getFactory($route)->create($key)->reset();
    }

    public function resetIfConfigured(string $route, string $key): void
    {
        $factory = $this->factories[$route] ?? null;

        $factory?->create($key)->reset();
    }

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'salesChannelId', parameterType: '?string', defaultValue: null, description: 'Sales channel id used to resolve sales-channel scoped limits for the system_config policy.')]
    public function ensureAccepted(string $route, string $key/* , ?string $salesChannelId = null */): void
    {
        /** @deprecated tag:v6.8.0 - Remove next line as $salesChannelId will become a part of method signature */
        /** @var string|null $salesChannelId */
        $salesChannelId = \func_get_args()[2] ?? null;

        $limiter = $this->getFactory($route)->create($key, $salesChannelId)->consume();

        if (!$limiter->isAccepted()) {
            throw RateLimiterException::limitExceeded($limiter->getRetryAfter()->getTimestamp());
        }
    }

    public function ensureAcceptedIfConfigured(string $route, string $key): void
    {
        $factory = $this->factories[$route] ?? null;

        if ($factory === null) {
            return;
        }

        $limiter = $factory->create($key)->consume();

        if (!$limiter->isAccepted()) {
            throw RateLimiterException::limitExceeded($limiter->getRetryAfter()->getTimestamp());
        }
    }

    public function registerLimiterFactory(string $route, RateLimiterFactory $factory): void
    {
        $this->factories[$route] = $factory;
    }

    private function getFactory(string $route): RateLimiterFactory
    {
        $factory = $this->factories[$route] ?? null;

        if ($factory === null) {
            throw RateLimiterException::factoryNotFound($route);
        }

        return $factory;
    }
}
