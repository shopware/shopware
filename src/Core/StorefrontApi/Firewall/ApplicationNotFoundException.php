<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Firewall;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class ApplicationNotFoundException extends UsernameNotFoundException
{
    public function getMessageKey()
    {
        return 'No application found for provided token.';
    }
}
