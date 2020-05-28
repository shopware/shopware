<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

class AclAnnotationValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'validate'];
    }

    public function validate(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $privileges = $request->attributes->get('_acl');

        if (!$privileges) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if ($context === null) {
            throw new InsufficientAuthenticationException('Missing privileges');
        }

        /* @var Context $context */
        foreach ($privileges as $privilege) {
            if (!$context->isAllowed($privilege)) {
                throw new InsufficientAuthenticationException(
                    sprintf('Missing privilege %s', $privilege)
                );
            }
        }
    }
}
