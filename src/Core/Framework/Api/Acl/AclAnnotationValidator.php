<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AclAnnotationValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => [
                ['validate', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ];
    }

    public function validate(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        $acl = $request->attributes->get('_acl');

        if (!$acl || !($acl instanceof Acl)) {
            return;
        }

        $privileges = $acl->getValue();

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if ($context === null) {
            throw new MissingPrivilegeException([]);
        }

        /* @var Context $context */
        foreach ($privileges as $privilege) {
            if (!$context->isAllowed($privilege)) {
                throw new MissingPrivilegeException([$privilege]);
            }
        }
    }
}
