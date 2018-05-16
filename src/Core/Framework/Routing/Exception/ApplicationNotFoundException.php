<?php declare(strict_types=1);

namespace Shopware\Framework\Routing\Exception;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class ApplicationNotFoundException extends UsernameNotFoundException
{
    public function getMessageKey()
    {
        return 'No application found for provided token.';
    }
}
