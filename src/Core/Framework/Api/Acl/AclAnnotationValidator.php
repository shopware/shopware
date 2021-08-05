<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AclAnnotationValidator implements EventSubscriberInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

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

        foreach ($privileges as $privilege) {
            if ($privilege === 'app') {
                if ($context->isAllowed('app.all')) {
                    return;
                }

                $privilege = $this->getAppPrivilege($request);
            }

            if (!$context->isAllowed($privilege)) {
                throw new MissingPrivilegeException([$privilege]);
            }
        }
    }

    private function getAppPrivilege(Request $request): string
    {
        $actionId = $request->get('id');

        if (empty($actionId)) {
            throw new MissingPrivilegeException();
        }

        $appName = $this->connection->fetchOne(
            '
                SELECT `app`.`name` AS `name`
                FROM `app`
                INNER JOIN `app_action_button` ON `app`.`id` = `app_action_button`.`app_id`
                WHERE `app_action_button`.`id` = :id
            ',
            ['id' => Uuid::fromHexToBytes($actionId)],
        );

        return 'app.' . $appName;
    }
}
