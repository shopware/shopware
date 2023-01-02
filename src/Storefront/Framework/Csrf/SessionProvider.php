<?php
declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @package storefront
 *
 * We have this extra class to have the session object injected in a lazy way to not initialize it on injection
 *
 * @internal
 *
 * @deprecated tag:v6.5.0 Will be removed with upgrade to Symfony 6.0
 */
#[Package('storefront')]
class SessionProvider
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', RequestStack::class . '::getSession()')
        );

        $this->session = $session;
    }

    public function getSession(): SessionInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', RequestStack::class . '::getSession()')
        );

        return $this->session;
    }
}
