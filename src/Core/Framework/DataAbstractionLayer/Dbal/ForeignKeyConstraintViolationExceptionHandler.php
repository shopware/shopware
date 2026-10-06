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

        if (!\preg_match('/CONSTRAINT [`"]fk(?<separator>\.|__)(?<entity>.+?)\\k<separator>(?<field>[^`"]+)[`"] FOREIGN KEY/i', $e->getMessage(), $matches)) {
            return $this->createException(null, null, null);
        }

        try {
            $definition = $this->registry->getByEntityName($matches['entity']);
            $field = $definition->getFields()->getByStorageName($matches['field']);
        } catch (\Throwable) {
            return $this->createException(null, null, null);
        }

        if (!$field instanceof FkField) {
            return $this->createException(null, null, null);
        }

        return $this->createException(
            $matches['entity'],
            $field->getPropertyName(),
            $field->getReferenceDefinition()->getEntityName(),
        );
    }

    private function createException(?string $entity, ?string $field, ?string $referencedEntity): InvalidForeignKeyReferenceException
    {
        if ($entity === null || $field === null || $referencedEntity === null) {
            $message = 'A referenced entity or version does not exist.';
            $template = $message;
            $parameters = [];
            $propertyPath = '';
        } else {
            $message = \sprintf('The field "%s" on "%s" references a missing "%s" entity or version.', $field, $entity, $referencedEntity);
            $template = 'The field "{{ field }}" on "{{ entity }}" references a missing "{{ reference }}" entity or version.';
            $parameters = ['{{ field }}' => $field, '{{ entity }}' => $entity, '{{ reference }}' => $referencedEntity];
            $propertyPath = '/' . $field;
        }

        return new InvalidForeignKeyReferenceException(new ConstraintViolationList([
            new ConstraintViolation($message, $template, $parameters, null, $propertyPath, null, null, 'FRAMEWORK__INVALID_FOREIGN_KEY_REFERENCE'),
        ]));
    }
}
