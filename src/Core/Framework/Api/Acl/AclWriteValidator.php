<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Api\Acl\Event\CommandAclValidationEvent;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('system-settings')]
class AclWriteValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();
        $source = $event->getContext()->getSource();
        if ($source instanceof AdminSalesChannelApiSource) {
            $context = $source->getOriginalContext();
            $source = $context->getSource();
        }

        if ($context->getScope() === Context::SYSTEM_SCOPE || !$source instanceof AdminApiSource || $source->isAdmin()) {
            return;
        }

        $commands = $event->getCommands();
        $missingPrivileges = [];

        foreach ($commands as $command) {
            $resource = $command->getDefinition()->getEntityName();
            $privilege = $command->getPrivilege();

            if ($privilege === null) {
                continue;
            }

            if (is_subclass_of($command->getDefinition(), EntityTranslationDefinition::class)) {
                $resource = $command->getDefinition()->getParentDefinition()->getEntityName();

                if ($privilege !== AclRoleDefinition::PRIVILEGE_DELETE) {
                    $privilege = $this->getPrivilegeForParentWriteOperation($command, $commands);
                }
            }

            if (!$source->isAllowed($resource . ':' . $privilege)) {
                $missingPrivileges[] = $resource . ':' . $privilege;
            }

            $event = new CommandAclValidationEvent($missingPrivileges, $source, $command);
            $this->eventDispatcher->dispatch($event);
            /**
             * @var list<string> $missingPrivileges
             */
            $missingPrivileges = $event->getMissingPrivileges();
        }

        $this->tryToThrow($missingPrivileges);
    }

    /**
     * @param list<string> $missingPrivileges
     */
    private function tryToThrow(array $missingPrivileges): void
    {
        if (!empty($missingPrivileges)) {
            throw ApiException::missingPrivileges($missingPrivileges);
        }
    }

    /**
     * @param WriteCommand[] $commands
     */
    private function getPrivilegeForParentWriteOperation(WriteCommand $command, array $commands): string
    {
        $pathSuffix = '/translations/' . Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);
        $parentCommandPath = str_replace($pathSuffix, '', $command->getPath());
        $parentCommand = $this->findCommandByPath($parentCommandPath, $commands);

        // writes to translation need privilege from parent command
        // if we update e.g. a product and add translations for a new language
        // the writeCommand on the translation would be an insert
        if ($parentCommand) {
            return (string) $parentCommand->getPrivilege();
        }

        // if we don't have a parentCommand it must be a update,
        // because the parentEntity must already exist
        return AclRoleDefinition::PRIVILEGE_UPDATE;
    }

    /**
     * @param WriteCommand[] $commands
     */
    private function findCommandByPath(string $commandPath, array $commands): ?WriteCommand
    {
        foreach ($commands as $command) {
            if ($command->getPath() === $commandPath) {
                return $command;
            }
        }

        return null;
    }
}
