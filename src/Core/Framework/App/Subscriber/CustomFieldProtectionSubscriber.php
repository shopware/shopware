<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
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

    public function __construct(
        private readonly Connection $connection,
        private readonly WriteOutcome $writeOutcome,
    ) {
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

        $customFieldSets = $this->writeOutcome->touchedRows($event->getCommandsForEntity(CustomFieldSetDefinition::ENTITY_NAME), ['app_id']);
        $appIntegrationIds = $this->fetchAppIntegrationIds($customFieldSets);

        foreach ($customFieldSets as $customFieldSet) {
            $appId = $customFieldSet->getStoredValue('app_id');
            if (!\is_string($appId) || !isset($appIntegrationIds[$appId])) {
                continue;
            }

            if ($integrationId !== $appIntegrationIds[$appId]) {
                $this->addViolation($violationList);
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
     * @param list<OutcomeRow> $customFieldSets
     *
     * @return array<string, string> the integration id of each app that owns one of the stored sets, keyed by app id in storage format
     */
    private function fetchAppIntegrationIds(array $customFieldSets): array
    {
        $appIds = array_filter(array_map(static fn (OutcomeRow $customFieldSet): mixed => $customFieldSet->getStoredValue('app_id'), $customFieldSets));
        if ($appIds === []) {
            return [];
        }

        return $this->connection->fetchAllKeyValue(
            'SELECT `id`, LOWER(HEX(`integration_id`)) FROM `app` WHERE `id` IN (:ids)',
            ['ids' => array_values(array_unique($appIds))],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    private function addViolation(ConstraintViolationList $violationList): void
    {
        $violationList->add(
            $this->buildViolation(
                'No permissions to %privilege%".',
                ['%privilege%' => 'write:custom_field_set'],
                '/' . CustomFieldSetDefinition::ENTITY_NAME,
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
