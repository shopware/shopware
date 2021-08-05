<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Validation;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class SalesChannelValidator implements EventSubscriberInterface
{
    private const ID_KEY = 'id';
    private const LANGUAGE_ID_KEY = 'language_id';
    private const SALES_CHANNEL_ID_KEY = 'sales_channel_id';
    private const SALES_CHANNEL_ID_TEMPLATE_PLACEHOLDER = '{{ salesChannelId }}';
    private const PROPERTY_PATH = '/';

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id for SalesChannel with id "%s".';
    private const DELETE_VALIDATION_CODE = 'SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID';
    private const INSERT_VALIDATION_MESSAGE = 'SalesChannel with id "%s" has no default language id set.';
    private const INSERT_VALIDATION_CODE = 'SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID';

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preventInconsistentDefaultLanguages',
        ];
    }

    public function preventInconsistentDefaultLanguages(PreWriteValidationEvent $event): void
    {
        $newSalesChannelIds = [];
        $languageIds = [];

        foreach ($event->getCommands() as $writeCommand) {
            if ($writeCommand instanceof DeleteCommand) {
                $this->validateDeleteCommand($writeCommand, $event);

                continue;
            }

            if (!$writeCommand instanceof InsertCommand) {
                continue;
            }

            $this->storeInsertIdsForValidation($writeCommand, $newSalesChannelIds, $languageIds);
        }

        $this->validateInsertCommands($languageIds, $newSalesChannelIds, $event);
    }

    private function validateDeleteCommand(WriteCommand $writeCommand, PreWriteValidationEvent $event): void
    {
        if (!$writeCommand->getDefinition() instanceof SalesChannelLanguageDefinition) {
            return;
        }

        $primaryKey = $writeCommand->getPrimaryKey();
        $languageId = Uuid::fromBytesToHex($primaryKey[self::LANGUAGE_ID_KEY]);

        if ($languageId !== Defaults::LANGUAGE_SYSTEM) {
            return;
        }

        $salesChannelId = Uuid::fromBytesToHex($primaryKey[self::SALES_CHANNEL_ID_KEY]);
        $this->addWriteException(EntityWriteResult::OPERATION_DELETE, $salesChannelId, $event);
    }

    private function validateInsertCommands(
        array $languageIds,
        array $newSalesChannelIds,
        PreWriteValidationEvent $event
    ): void {
        foreach ($newSalesChannelIds as $newSalesChannelId) {
            if ($this->insertCommandIsValid($newSalesChannelId, $languageIds)) {
                continue;
            }

            $this->addWriteException(EntityWriteResult::OPERATION_INSERT, $newSalesChannelId, $event);
        }
    }

    private function insertCommandIsValid(string $salesChannelId, array $languageIds): bool
    {
        return empty($languageIds[$salesChannelId])
            || \in_array(Defaults::LANGUAGE_SYSTEM, $languageIds[$salesChannelId], true);
    }

    private function storeInsertIdsForValidation(WriteCommand $writeCommand, array &$newSalesChannelIds, array &$languageIds): void
    {
        $definition = $writeCommand->getDefinition();

        if ($definition instanceof SalesChannelDefinition) {
            $salesChannelId = $writeCommand->getPrimaryKey()[self::ID_KEY];
            $newSalesChannelIds[] = Uuid::fromBytesToHex($salesChannelId);

            return;
        }

        if ($definition instanceof SalesChannelLanguageDefinition) {
            $payload = $writeCommand->getPayload();

            $salesChannelId = Uuid::fromBytesToHex($payload[self::SALES_CHANNEL_ID_KEY]);
            $languageId = Uuid::fromBytesToHex($payload[self::LANGUAGE_ID_KEY]);

            $languageIds[$salesChannelId][] = $languageId;
        }
    }

    private function addWriteException(string $operation, string $salesChannelId, PreWriteValidationEvent $event): void
    {
        if ($operation === EntityWriteResult::OPERATION_DELETE) {
            $message = self::DELETE_VALIDATION_MESSAGE;
            $code = self::DELETE_VALIDATION_CODE;
        } else {
            $message = self::INSERT_VALIDATION_MESSAGE;
            $code = self::INSERT_VALIDATION_CODE;
        }

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            sprintf($message, $salesChannelId),
            sprintf($message, self::SALES_CHANNEL_ID_TEMPLATE_PLACEHOLDER),
            [self::SALES_CHANNEL_ID_TEMPLATE_PLACEHOLDER => $salesChannelId],
            null,
            self::PROPERTY_PATH,
            null,
            null,
            $code
        ));

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }
}
