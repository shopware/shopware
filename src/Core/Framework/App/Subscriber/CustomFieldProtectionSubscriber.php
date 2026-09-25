<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Tests\Integration\Core\Framework\App\Subscriber\CustomFieldProtectionSubscriberTest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @see CustomFieldProtectionSubscriberTest
 */
#[Package('framework')]
class CustomFieldProtectionSubscriber implements EventSubscriberInterface
{
    final public const VIOLATION_NO_PERMISSION = 'no_permission_violation';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'checkWrite',
        ];
    }

    public function checkWrite(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();

        if ($context->getSource() instanceof SystemSource || $context->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        $integrationId = $this->getIntegrationId($context);
        $violationList = new ConstraintViolationList();

        $commands = array_values(array_filter(
            $event->getCommandsForEntity(CustomFieldSetDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => !$command instanceof InsertCommand
        ));

        $appIntegrationIds = $this->fetchIntegrationIdsOfAssociatedApps($commands);

        foreach ($commands as $command) {
            $appIntegrationId = $appIntegrationIds[Uuid::fromBytesToHex($command->getPrimaryKey()['id'])] ?? null;
            if (!$appIntegrationId) {
                continue;
            }

            if ($integrationId !== $appIntegrationId) {
                $this->addViolation($violationList, $command);
            }
        }
        if ($violationList->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violationList));
        }
    }

    private function getIntegrationId(Context $context): ?string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            return null;
        }

        return $source->getIntegrationId();
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string, string> custom field set id to the integration id of its app, both hex
     */
    private function fetchIntegrationIdsOfAssociatedApps(array $commands): array
    {
        if ($commands === []) {
            return [];
        }

        $ids = array_map(static fn (WriteCommand $command): string => $command->getPrimaryKey()['id'], $commands);

        return $this->connection->fetchAllKeyValue('
            SELECT LOWER(HEX(`custom_field_set`.`id`)), LOWER(HEX(`app`.`integration_id`))
            FROM `app`
            INNER JOIN `custom_field_set` ON `custom_field_set`.`app_id` = `app`.`id`
            WHERE `custom_field_set`.`id` IN (:customFieldSetIds)
        ', ['customFieldSetIds' => $ids], ['customFieldSetIds' => ArrayParameterType::BINARY]);
    }

    private function addViolation(ConstraintViolationList $violationList, WriteCommand $command): void
    {
        $violationList->add(
            $this->buildViolation(
                'No permissions to %privilege%".',
                ['%privilege%' => 'write:custom_field_set'],
                '/' . $command->getEntityName(),
                self::VIOLATION_NO_PERMISSION
            )
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        ?string $propertyPath = null,
        ?string $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            null,
            null,
            $code
        );
    }
}
