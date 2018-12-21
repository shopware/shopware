<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Shopware\Core\Framework\Validation\WriteCommandValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class TranslationValidator implements WriteCommandValidatorInterface
{
    public const VIOLATION_DELETE_SYSTEM_TRANSLATION = 'delete-system-translation-violation';

    public function preValidate(array $writeCommands, WriteContext $context): void
    {
        $violations = new ConstraintViolationList();
        $violations->addAll($this->getDeletedSystemTranslationViolations($writeCommands));

        $this->tryToThrow($violations);
    }

    /**
     * @param WriteCommandInterface[] $writeCommands *
     */
    public function postValidate(array $writeCommands, WriteContext $context): void
    {
    }

    private function getDeletedSystemTranslationViolations(array $writeCommands): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();

        foreach ($writeCommands as $writeCommand) {
            if (!$writeCommand instanceof DeleteCommand) {
                continue;
            }
            $pk = $writeCommand->getPrimaryKey();
            if (!isset($pk['language_id'])) {
                continue;
            }

            $def = $writeCommand->getDefinition();
            if (!is_subclass_of($def, EntityTranslationDefinition::class)) {
                continue;
            }

            if (Uuid::fromBytesToHex($pk['language_id']) !== Defaults::LANGUAGE_SYSTEM) {
                continue;
            }

            $fks = $this->getFkFields($def);
            $id = Uuid::fromBytesToHex($pk[$fks['id']->getStorageName()]);
            $violations->add(
                $this->buildViolation(
                    'Cannot delete system translation',
                    ['{{ id }}' => $id],
                    null,
                    '/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM,
                    [$id, Defaults::LANGUAGE_SYSTEM],
                    self::VIOLATION_DELETE_SYSTEM_TRANSLATION
                )
            );
        }

        return $violations;
    }

    /**
     * @param string|EntityDefinition $definition
     *
     * @return FkField[]
     */
    private function getFkFields($definition): array
    {
        $rootEntity = $definition::getParentDefinitionClass();
        if (!$rootEntity) {
            throw new \RuntimeException(sprintf('`%s` should implement `getRootEntity`', $definition));
        }
        $idStorageName = $rootEntity::getEntityName() . '_id';
        $versionIdStorageName = $rootEntity::getEntityName() . '_version_id';

        $pks = $definition::getPrimaryKeys();
        $idField = $pks->getByStorageName($idStorageName);
        if (!$idField || !$idField instanceof FkField) {
            throw new \RuntimeException(sprintf('`%s` primary key should have column `%s`', $definition, $idStorageName));
        }
        $fields = [
            'id' => $idField,
        ];

        $versionIdField = $pks->getByStorageName($versionIdStorageName);
        if ($versionIdField && $versionIdField instanceof FkField) {
            $fields['version'] = $versionIdField;
        }

        return $fields;
    }

    /**
     * @throws ConstraintViolationException
     */
    private function tryToThrow(ConstraintViolationListInterface $violations): void
    {
        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations);
        }
    }

    private function buildViolation(string $messageTemplate, array $parameters, $root = null, string $propertyPath = null, $invalidValue = null, $code = null): ConstraintViolationInterface
    {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            $root,
            $propertyPath,
            $invalidValue,
            $plural = null,
            $code,
            $constraint = null,
            $cause = null
        );
    }
}
