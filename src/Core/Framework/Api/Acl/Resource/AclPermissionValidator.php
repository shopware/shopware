<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Resource;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class AclPermissionValidator implements EventSubscriberInterface
{
    public const VIOLATION_NO_PERMISSION = 'no_permission_violation';

    public static function getSubscribedEvents()
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();
        $source = $event->getContext()->getSource();
        if (!$source instanceof AdminApiSource) {
            return;
        }

        $violationList = new ConstraintViolationList();

        foreach ($commands as $command) {
            $resource = $command->getDefinition()->getEntityName();
            if (is_subclass_of($command->getDefinition(), EntityTranslationDefinition::class)) {
                $resource = $command->getDefinition()->getParentDefinition()->getEntityName();
            }

            $privilege = $command->getPrivilege();

            if (!$source->isAllowed($resource, $privilege)) {
                $this->violates($privilege, $resource, $command, $violationList);
            }
        }

        $this->tryToThrow($violationList);
    }

    private function tryToThrow(ConstraintViolationList $violations): void
    {
        if ($violations->count() > 0) {
            $violationException = new WriteConstraintViolationException($violations);

            throw new AccessDeniedHttpException('You don\'t have all necessary permissions.', $violationException);
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
                'No permissions to %privilege% "%resource%".',
                ['%privilege%' => $privilege, '%resource%' => $resource],
                null,
                '/' . $command->getDefinition()->getEntityName(),
                null,
                self::VIOLATION_NO_PERMISSION
            )
        );
    }
}
