<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * aclRoleIds is either left out, to use all of the owner's roles, or a list of role ids.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\WebhookApiTest
 */
#[Package('framework')]
final readonly class WebhookAclRoleIdsWriteValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $aclRoleIds = $command->getPayload()['acl_role_ids'] ?? null;
            if (!\is_string($aclRoleIds)) {
                continue;
            }

            $aclRoleIds = Json::decodeToList($aclRoleIds);
            if ($aclRoleIds === []) {
                $message = 'aclRoleIds must not be empty. Leave it out to use all of the owner\'s roles.';
            } elseif (\in_array(null, $aclRoleIds, true) || \in_array('', $aclRoleIds, true)) {
                $message = 'aclRoleIds must not contain empty entries.';
            } else {
                continue;
            }

            $event->getExceptions()->add(new WriteConstraintViolationException(
                new ConstraintViolationList([new ConstraintViolation($message, $message, [], null, '/aclRoleIds', $aclRoleIds)]),
                $command->getPath()
            ));
        }
    }
}
