<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidForeignKeyReferenceException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
class ForeignKeyConstraintViolationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(private readonly DefinitionInstanceRegistry $registry)
    {
    }

    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_LATE;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (!$e instanceof ForeignKeyConstraintViolationException) {
            return null;
        }

        if (!\str_contains($e->getMessage(), 'Integrity constraint violation: 1452')) {
            return null;
        }

        $originalMessage = $e->getMessage();

        if (!\preg_match('/CONSTRAINT [`"]fk(?<separator>\.|__)(?<entity>.+?)\\k<separator>(?<field>[^`"]+)[`"] FOREIGN KEY/i', $e->getMessage(), $matches)) {
            return $this->createGenericException($originalMessage);
        }

        try {
            $definition = $this->registry->getByEntityName($matches['entity']);
            $field = $definition->getFields()->getByStorageName($matches['field']);
        } catch (\Throwable) {
            return $this->createGenericException($originalMessage);
        }

        if (!$field instanceof FkField) {
            return $this->createGenericException($originalMessage);
        }

        return $this->createSpecificException(
            $matches['entity'],
            $field->getPropertyName(),
            $field->getReferenceDefinition()->getEntityName(),
            $originalMessage,
        );
    }

    private function createGenericException(string $originalMessage): InvalidForeignKeyReferenceException
    {
        $template = 'A referenced entity or version does not exist. Original error: {{ originalMessage }}';
        $message = \sprintf('A referenced entity or version does not exist. Original error: %s', $originalMessage);

        return new InvalidForeignKeyReferenceException(new ConstraintViolationList([
            new ConstraintViolation($message, $template, ['{{ originalMessage }}' => $originalMessage], null, '', null, null, InvalidForeignKeyReferenceException::ERROR_CODE),
        ]));
    }

    private function createSpecificException(string $entity, string $field, string $referencedEntity, string $originalMessage): InvalidForeignKeyReferenceException
    {
        $template = 'The field "{{ field }}" on "{{ entity }}" references a missing "{{ reference }}" entity or version. Original error: {{ originalMessage }}';
        $message = \sprintf('The field "%s" on "%s" references a missing "%s" entity or version. Original error: %s', $field, $entity, $referencedEntity, $originalMessage);
        $parameters = [
            '{{ field }}' => $field,
            '{{ entity }}' => $entity,
            '{{ reference }}' => $referencedEntity,
            '{{ originalMessage }}' => $originalMessage,
        ];
        $propertyPath = '/' . $field;

        return new InvalidForeignKeyReferenceException(new ConstraintViolationList([
            new ConstraintViolation($message, $template, $parameters, null, $propertyPath, null, null, InvalidForeignKeyReferenceException::ERROR_CODE),
        ]));
    }
}
