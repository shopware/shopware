<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\EntityProtection;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @internal
 */
#[Package('core')]
class EntityProtectionValidator implements EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validateWriteCommands',
            EntitySearchedEvent::class => 'validateEntitySearch',
        ];
    }

    /**
     * @param list<array{entity: string, value: string|null, definition: EntityDefinition, field: Field|null}> $pathSegments
     * @param array<string> $protections FQCN of the protections that need to be validated
     */
    public function validateEntityPath(array $pathSegments, array $protections, Context $context): void
    {
        foreach ($pathSegments as $pathSegment) {
            /** @var EntityDefinition $definition */
            $definition = $pathSegment['definition'];

            foreach ($protections as $protection) {
                $protectionInstance = $definition->getProtections()->get($protection);
                if (!$protectionInstance || $protectionInstance->isAllowed($context->getScope())) {
                    continue;
                }

                throw new AccessDeniedHttpException(
                    sprintf('API access for entity "%s" not allowed.', $pathSegment['entity'])
                );
            }
        }
    }

    public function validateEntitySearch(EntitySearchedEvent $event): void
    {
        $definition = $event->getDefinition();
        $readProtection = $definition->getProtections()->get(ReadProtection::class);
        $context = $event->getContext();

        if ($readProtection && !$readProtection->isAllowed($context->getScope())) {
            throw new AccessDeniedHttpException(
                sprintf(
                    'Read access to entity "%s" not allowed for scope "%s".',
                    $definition->getEntityName(),
                    $context->getScope()
                )
            );
        }

        $this->validateCriteriaAssociation(
            $definition,
            $event->getCriteria()->getAssociations(),
            $context
        );
    }

    public function validateWriteCommands(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            // Don't validate commands that fake operations on DB level, e.g. cascade deletes
            if (!$command->isValid()) {
                continue;
            }

            $writeProtection = $command->getDefinition()->getProtections()->get(WriteProtection::class);
            if ($writeProtection && !$writeProtection->isAllowed($event->getContext()->getScope())) {
                throw new AccessDeniedHttpException(
                    sprintf(
                        'Write access to entity "%s" are not allowed in scope "%s".',
                        $command->getDefinition()->getEntityName(),
                        $event->getContext()->getScope()
                    )
                );
            }
        }
    }

    /**
     * @param array<string, Criteria> $associations
     */
    private function validateCriteriaAssociation(EntityDefinition $definition, array $associations, Context $context): void
    {
        /** @var Criteria $criteria */
        foreach ($associations as $associationName => $criteria) {
            $field = $definition->getField($associationName);
            if (!$field instanceof AssociationField) {
                continue;
            }

            $associationDefinition = $field->getReferenceDefinition();
            $readProtection = $associationDefinition->getProtections()->get(ReadProtection::class);
            if ($readProtection && !$readProtection->isAllowed($context->getScope())) {
                throw new AccessDeniedHttpException(
                    sprintf(
                        'Read access to nested association "%s" on entity "%s" not allowed for scope "%s".',
                        $associationName,
                        $definition->getEntityName(),
                        $context->getScope()
                    )
                );
            }

            $this->validateCriteriaAssociation($associationDefinition, $criteria->getAssociations(), $context);
        }
    }
}
