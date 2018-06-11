<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class TouchpointNotFoundException extends UsernameNotFoundException
{
    public function getMessageKey()
    {
        return 'No touchpoint found for provided token.';
    }
}
