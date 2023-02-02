<?php
declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * We have this extra class to have the session object injected in a lazy way to not initialize it on injection
 *
 * @internal
 *
 * @deprecated Will be removed with upgrade to Symfony 6.0
 */
class SessionProvider
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function getSession(): SessionInterface
    {
        return $this->session;
    }
}
