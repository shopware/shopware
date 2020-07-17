<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class AclWriteValidator implements EventSubscriberInterface
{
    public const VIOLATION_NO_PERMISSION = 'no_permission_violation';

    public static function getSubscribedEvents()
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        $commands = $event->getCommands();
        $source = $event->getContext()->getSource();
        if (!$source instanceof AdminApiSource || $source->isAdmin()) {
            return;
        }

        $violationList = new ConstraintViolationList();

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
                $this->violates($privilege, $resource, $command, $violationList);
            }
        }

        $this->tryToThrow($violationList);
    }

    private function tryToThrow(ConstraintViolationList $violations): void
    {
        if ($violations->count() > 0) {
            throw new WriteConstraintViolationException($violations);
        }
    }

    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        $root = null,
        ?string $propertyPath = null,
        $invalidValue = null,
        ?string $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            $root,
            $propertyPath,
            $invalidValue,
            null,
            $code
        );
    }

    private function violates(
        string $privilege,
        string $resource,
        WriteCommand $command,
        ConstraintViolationList $violationList
    ): void {
        $violationList->add(
            $this->buildViolation(
                'No permissions to %privilege%".',
                ['%privilege%' => $resource . ':' . $privilege],
                null,
                '/' . $command->getDefinition()->getEntityName(),
                null,
                self::VIOLATION_NO_PERMISSION
            )
        );
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
            return $parentCommand->getPrivilege();
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
