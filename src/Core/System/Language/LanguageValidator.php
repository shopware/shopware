<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('core')]
class LanguageValidator implements EventSubscriberInterface
{
    final public const VIOLATION_PARENT_HAS_PARENT = 'parent_has_parent_violation';

    final public const VIOLATION_CODE_REQUIRED_FOR_ROOT_LANGUAGE = 'code_required_for_root_language';

    final public const VIOLATION_DELETE_DEFAULT_LANGUAGE = 'delete_default_language_violation';

    final public const VIOLATION_DEFAULT_LANGUAGE_PARENT = 'default_language_parent_violation';

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
            PostWriteValidationEvent::class => 'postValidate',
        ];
    }

    public function postValidate(PostWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();
        $affectedIds = $this->getAffectedIds($commands);
        if (\count($affectedIds) === 0) {
            return;
        }

        $violations = new ConstraintViolationList();
        $violations->addAll($this->getInheritanceViolations($affectedIds));
        $violations->addAll($this->getMissingTranslationCodeViolations($affectedIds));

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();

        foreach ($commands as $command) {
            $violations = new ConstraintViolationList();

            if ($command instanceof CascadeDeleteCommand || $command->getDefinition()->getClass() !== LanguageDefinition::class) {
                continue;
            }

            $pk = $command->getPrimaryKey();
            $id = mb_strtolower(Uuid::fromBytesToHex($pk['id']));

            if ($command instanceof DeleteCommand && $id === Defaults::LANGUAGE_SYSTEM) {
                $violations->add(
                    $this->buildViolation(
                        'The default language {{ id }} cannot be deleted.',
                        ['{{ id }}' => $id],
                        '/' . $id,
                        $id,
                        self::VIOLATION_DELETE_DEFAULT_LANGUAGE
                    )
                );
            }

            if ($command instanceof UpdateCommand && $id === Defaults::LANGUAGE_SYSTEM) {
                $payload = $command->getPayload();
                if (\array_key_exists('parent_id', $payload) && $payload['parent_id'] !== null) {
                    $violations->add(
                        $this->buildViolation(
                            'The default language {{ id }} cannot inherit from another language.',
                            ['{{ id }}' => $id],
                            '/parentId',
                            $payload['parent_id'],
                            self::VIOLATION_DEFAULT_LANGUAGE_PARENT
                        )
                    );
                }
            }

            if ($violations->count() > 0) {
                $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
            }
        }
    }

    /**
     * @param array<string> $affectedIds
     */
    private function getInheritanceViolations(array $affectedIds): ConstraintViolationList
    {
        $statement = $this->connection->executeQuery(
            'SELECT child.id
             FROM language child
             INNER JOIN language parent ON parent.id = child.parent_id
             WHERE (child.id IN (:ids) OR child.parent_id IN (:ids))
             AND parent.parent_id IS NOT NULL',
            ['ids' => $affectedIds],
            ['ids' => ArrayParameterType::STRING]
        );
        $ids = $statement->fetchFirstColumn();

        $violations = new ConstraintViolationList();
        foreach ($ids as $binId) {
            $id = Uuid::fromBytesToHex($binId);
            $violations->add(
                $this->buildViolation(
                    'Language inheritance limit for the child {{ id }} exceeded. A Language must not be nested deeper than one level.',
                    ['{{ id }}' => $id],
                    '/' . $id . '/parentId',
                    $id,
                    self::VIOLATION_PARENT_HAS_PARENT
                )
            );
        }

        return $violations;
    }

    /**
     * @param array<string> $affectedIds
     */
    private function getMissingTranslationCodeViolations(array $affectedIds): ConstraintViolationList
    {
        $statement = $this->connection->executeQuery(
            'SELECT lang.id
             FROM language lang
             LEFT JOIN locale l ON lang.translation_code_id = l.id
             WHERE l.id IS NULL # no translation code
             AND lang.parent_id IS NULL # root
             AND lang.id IN (:ids)',
            ['ids' => $affectedIds],
            ['ids' => ArrayParameterType::STRING]
        );
        $ids = $statement->fetchFirstColumn();

        $violations = new ConstraintViolationList();
        foreach ($ids as $binId) {
            $id = Uuid::fromBytesToHex($binId);
            $violations->add(
                $this->buildViolation(
                    'Root language {{ id }} requires a translation code',
                    ['{{ id }}' => $id],
                    '/' . $id . '/translationCodeId',
                    $id,
                    self::VIOLATION_CODE_REQUIRED_FOR_ROOT_LANGUAGE
                )
            );
        }

        return $violations;
    }

    /**
     * @param WriteCommand[] $commands
     *
     * @return array<string>
     */
    private function getAffectedIds(array $commands): array
    {
        $ids = [];
        foreach ($commands as $command) {
            if ($command->getDefinition()->getClass() !== LanguageDefinition::class) {
                continue;
            }
            if ($command instanceof InsertCommand || $command instanceof UpdateCommand) {
                $ids[] = $command->getPrimaryKey()['id'];
            }
        }

        return $ids;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        ?string $propertyPath = null,
        ?string $invalidValue = null,
        ?string $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            $invalidValue,
            null,
            $code
        );
    }
}
