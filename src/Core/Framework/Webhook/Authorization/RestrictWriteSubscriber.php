<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class RestrictWriteSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly WriteAuthorizer $authorizer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $commands = array_values(array_filter(
            $event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => !$command instanceof CascadeDeleteCommand
        ));

        $this->removeDuplicateAclRoleIds($commands);

        foreach ($this->authorizer->getViolations($commands, $event->getContext()) as $violation) {
            $event->getExceptions()->add($violation);
        }
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function removeDuplicateAclRoleIds(array $commands): void
    {
        foreach ($commands as $command) {
            $aclRoleIds = $command->getPayload()['acl_role_ids'] ?? null;
            if (\is_string($aclRoleIds)) {
                $command->addPayload('acl_role_ids', Json::encode(array_values(array_unique(Json::decodeToList($aclRoleIds)))));
            }
        }
    }
}
