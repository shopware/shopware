<?php declare(strict_types=1);

namespace Shopware\Rest\Firewall;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationSuccessListener
{
    /**
     * @var int
     */
    private $ttl;

    public function __construct(int $ttl)
    {
        $this->ttl = $ttl;
    }

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data['expiry'] = time() + $this->ttl;

        $event->setData($data);
    }
}
