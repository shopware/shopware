<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
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
    public const DELETE_SYSTEM_TRANSLATION_VIOLATION = 'delete-system-translation-violation';
    public const ORPHAN_TRANSLATION_VIOLATION = 'orphan-translation-violation';

    /**
     * @var Connection
     */
    private $connection;

    private static $fkFields = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

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
        $deletedRootTranslations = $this->getDeletedRootTranslations($writeCommands, $context);
        if (empty($deletedRootTranslations)) {
            return;
        }

        $violations = new ConstraintViolationList();
        foreach ($deletedRootTranslations as $definition => $pks) {
            $violations->addAll($this->validateTranslationDefinition($definition, $pks));
        }

        $this->tryToThrow($violations);
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
            $languageId = Defaults::LANGUAGE_SYSTEM;
            $violations->add(
                $this->buildViolation(
                    'Cannot delete system translation',
                    ['{{ id }}' => $id],
                    null,
                    '/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM,
                    [$id, $languageId],
                    self::DELETE_SYSTEM_TRANSLATION_VIOLATION
                )
            );
        }

        return $violations;
    }

    private function validateTranslationDefinition($definition, $pks): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();
        $fks = $this->getFkFields($definition);

        $orphans = $this->findOrphanTranslations($definition, $pks);
        foreach ($orphans as $pk) {
            $id = Uuid::fromBytesToHex($pk[$fks['id']->getStorageName()]);
            $languageId = Uuid::fromBytesToHex($pk['language_id']);

            $violations->add(
                $this->buildViolation(
                    'Cannot delete root translations because this would create an orphan',
                    ['{{ id }}' => $id],
                    null,
                    '/' . $id . '/translations/' . $languageId,
                    [$id, $languageId],
                    self::ORPHAN_TRANSLATION_VIOLATION
                )
            );
        }

        return $violations;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    private function findOrphanTranslations(string $definition, array $pks): array
    {
        $builder = $this->getBaseQuery($definition, $pks);
        $fks = $this->getFkFields($definition);
        if (!$builder || !isset($fks['id'])) {
            return [];
        }

        $versionCondition = 'TRUE';
        if (isset($fks['version'])) {
            $versionIdFieldName = $fks['version']->getStorageName();
            $versionCondition = sprintf('translation.`%s` = rootTranslation.`%s`', $versionIdFieldName, $versionIdFieldName);
        }

        $idFieldName = $fks['id']->getStorageName();
        $table = $definition::getEntityName();
        $builder
            ->addSelect('lang.parent_id as language_id')
            ->innerJoin('translation', 'language', 'lang', 'lang.id = translation.language_id')
            ->leftJoin(
                'translation',
                $table,
                'rootTranslation',
                'translation.`' . $idFieldName . '` = rootTranslation.`' . $idFieldName . '`
                 AND ' . $versionCondition . '
                 AND rootTranslation.language_id = lang.parent_id'
            )
            ->andWhere('lang.parent_id IS NOT NULL')
            ->andWhere('rootTranslation.language_id IS NULL')
            ->addGroupBy('lang.parent_id');

        return $builder->execute()->fetchAll();
    }

    private function getBaseQuery(string $definition, array $pks): ?QueryBuilder
    {
        $fks = $this->getFkFields($definition);
        if (!isset($fks['id'])) {
            return null;
        }

        $table = $definition::getEntityName();
        $idFieldName = $fks['id']->getStorageName();
        $affectedIds = \array_column($pks, $idFieldName);

        $builder = $this->connection->createQueryBuilder();
        $builder
            ->from($table, 'translation')
            ->select('translation.`' . $idFieldName . '`')
            ->andWhere('translation.`' . $idFieldName . '` IN (:affectedIds)')
            ->setParameter('affectedIds', $affectedIds, Connection::PARAM_STR_ARRAY)
            ->groupBy('translation.`' . $idFieldName . '`');

        if (isset($fks['version'])) {
            $versionIdFieldName = $fks['version']->getStorageName();
            $affectedVersionIds = \array_column($pks, $versionIdFieldName);

            $builder->addSelect('translation.`' . $versionIdFieldName . '`')
                ->andWhere('translation.`' . $versionIdFieldName . '` IN (:affectedVersionIds)')
                ->setParameter('affectedVersionIds', $affectedVersionIds, Connection::PARAM_STR_ARRAY)
                ->addGroupBy('translation.`' . $versionIdFieldName . '`');
        }

        return $builder;
    }

    private function getRootLanguages(WriteContext $context): array
    {
        return array_filter($context->getLanguages(), function ($lang) {
            return $lang['parentId'] === null;
        });
    }

    /**
     * @param WriteCommandInterface[] $writeCommands
     */
    private function getDeletedRootTranslations(array $writeCommands, WriteContext $context): array
    {
        $rootLanguages = $this->getRootLanguages($context);

        $deletedRootTranslations = [];
        foreach ($writeCommands as $writeCommand) {
            if (!$writeCommand instanceof DeleteCommand) {
                continue;
            }
            $def = $writeCommand->getDefinition();
            $pk = $writeCommand->getPrimaryKey();
            if (!isset($pk['language_id'])) {
                continue;
            }

            if (!is_subclass_of($def, EntityTranslationDefinition::class)) {
                continue;
            }

            if (!\in_array(Uuid::fromBytesToHex($pk['language_id']), $rootLanguages, true)) {
                continue;
            }

            if (!isset($rootTranslationDeletes[$def])) {
                $deletedRootTranslations[$def] = [];
            }
            $deletedRootTranslations[$def][] = $pk;
        }

        return $deletedRootTranslations;
    }

    /**
     * @return FkField[]
     */
    private function getFkFields($definition): array
    {
        if (isset(self::$fkFields[$definition])) {
            return self::$fkFields[$definition];
        }
        $idField = null;
        $idVersionField = null;

        $pks = $definition::getPrimaryKeys();
        if ($pks->count() !== 2 && $pks->count() !== 3) {
            return [];
        }

        foreach ($definition::getPrimaryKeys() as $field) {
            if ($field->getPropertyName() === 'languageId') {
                continue;
            }

            if ($field instanceof FkField) {
                if ($field instanceof ReferenceVersionField) {
                    $idVersionField = $field;
                } else {
                    $idField = $field;
                }
            }
        }

        $fields = [];
        if ($idField) {
            $fields['id'] = $idField;
        }
        if ($idVersionField) {
            $fields['version'] = $idVersionField;
        }

        return self::$fkFields[$definition] = $fields;
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
