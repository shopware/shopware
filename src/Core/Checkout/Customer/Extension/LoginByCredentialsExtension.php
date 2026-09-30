<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wraps `AccountService::loginByCredentials`. Allows full replacement of the
 * credential check (alternative credential store, sub-account, SSO): a listener
 * on the `.pre` event may verify the credentials itself, assign the resulting
 * context token to `$result` and call `stopPropagation()` to short-circuit the
 * core login.
 *
 * @extends Extension<string>
 */
#[Package('checkout')]
final class LoginByCredentialsExtension extends Extension
{
    public const NAME = 'account.login-by-credentials';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The e-mail address the login was requested for
         */
        public readonly string $email,
        /**
         * @public
         *
         * @description The plain-text password submitted with the login request
         */
        #[\SensitiveParameter]
        public readonly string $password,
        /**
         * @public
         *
         * @description The current sales-channel context
         */
        public readonly SalesChannelContext $context,
    ) {
    }
}
