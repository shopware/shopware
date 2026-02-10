<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentBaseConfigValidator implements EventSubscriberInterface
{
    final public const VIOLATION_REQUIRED = 'DOCUMENT_BASE_CONFIG__FIELD_REQUIRED';

    /**
     * The proper entity fields are validated by the definition, so we only validate the json config fields here
     */
    private const REQUIRED_CONFIG_FIELDS = [
        'pageSize',
        'pageOrientation',
        'itemsPerPage',
        'fileTypes',
    ];

    private const REQUIRED_ADDRESS_FIELDS = [
        'companyName',
        'companyStreet',
        'companyCountryId',
        'companyZipcode',
        'companyCity',
    ];

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
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            if ($command->getEntityName() !== DocumentBaseConfigDefinition::ENTITY_NAME) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $config = $this->resolveConfig($command);

            foreach (self::REQUIRED_CONFIG_FIELDS as $field) {
                $this->validateRequiredField(
                    $violations,
                    $config[$field] ?? null,
                    '/config/' . $field,
                    $field
                );
            }

            if (!empty($config['displayCompanyAddress']) || !empty($config['displayReturnAddress'])) {
                foreach (self::REQUIRED_ADDRESS_FIELDS as $field) {
                    $this->validateRequiredField(
                        $violations,
                        $config[$field] ?? null,
                        '/config/' . $field,
                        $field
                    );
                }
            }

            if ($violations->count() > 0) {
                $event->getExceptions()->add(
                    new WriteConstraintViolationException(
                        $violations,
                        $command->getPath()
                    )
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfig(WriteCommand $command): array
    {
        $payload = $command->getPayload();

        $newConfig = isset($payload['config'])
            ? json_decode((string) $payload['config'], true, 512, \JSON_THROW_ON_ERROR)
            : [];

        if ($command instanceof UpdateCommand) {
            $existingConfig = $this->findExistingConfig($command->getPrimaryKey()['id']);

            return array_merge($existingConfig, $newConfig);
        }

        return $newConfig;
    }

    /**
     * @return array<string, mixed>
     */
    private function findExistingConfig(string $id): array
    {
        $config = $this->connection->fetchOne(
            'SELECT `config` FROM `document_base_config` WHERE `id` = :id',
            ['id' => $id]
        );

        if ($config === false || $config === null) {
            return [];
        }

        return json_decode((string) $config, true, 512, \JSON_THROW_ON_ERROR) ?: [];
    }

    private function validateRequiredField(
        ConstraintViolationList $violations,
        mixed $value,
        string $propertyPath,
        string $fieldName,
    ): void {
        if ($value === null || $value === '' || $value === []) {
            $violations->add(
                $this->buildViolation(
                    'The field "{{ field }}" is required.',
                    ['{{ field }}' => $fieldName],
                    $propertyPath,
                    $value,
                )
            );
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        string $propertyPath,
        mixed $invalidValue,
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            $invalidValue,
            null,
            self::VIOLATION_REQUIRED,
        );
    }
}
