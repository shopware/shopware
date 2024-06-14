<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('core')]
class ParentRelationValidator implements EventSubscriberInterface
{
    final public const VIOLATION_PARENT_RELATION_DOES_NOT_ALLOW_SELF_REFERENCES = 'FRAMEWORK__PARENT_RELATION_DOES_NOT_ALLOW_SELF_REFERENCES';

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @throws WriteConstraintViolationException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();
        $writeCommands = $event->getCommands();
        $selfReferences = $this->containsSelfReferencingParent($writeCommands);

        if (empty($selfReferences)) {
            return;
        }

        $message = 'The %s entity with id "%s" can not reference to itself as parent.';

        foreach ($selfReferences as $selfReference) {
            $violations->add(new ConstraintViolation(
                sprintf($message, $selfReference['entity'], $selfReference['id']),
                sprintf($message, '{{ entity }}', '{{ id }}'),
                ['{{ entity }}' => $selfReference['entity'], '{{ id }}' => $selfReference['id']],
                null,
                $selfReference['path'] . '/parentId',
                null,
                null,
                self::VIOLATION_PARENT_RELATION_DOES_NOT_ALLOW_SELF_REFERENCES
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param WriteCommand[] $writeCommands
     *
     * @return list<array{id: string, entity: string, path: string}>
     */
    private function containsSelfReferencingParent(array $writeCommands): array
    {
        $selfReferences = [];

        foreach ($writeCommands as $command) {
            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());

            if (!$definition->isParentAware()) {
                continue;
            }

            $id = $command->getPrimaryKey()['id'];
            $commandData = $command->getPayload();

            if (isset($commandData['parent_id']) && $commandData['parent_id'] === $id) {
                $selfReferences[] = [
                    'id' => Uuid::fromBytesToHex($id),
                    'entity' => $command->getEntityName(),
                    'path' => $command->getPath(),
                ];
            }
        }

        return $selfReferences;
    }
}
