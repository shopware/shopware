<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerLanguageSalesChannelSubscriber implements EventSubscriberInterface
{
    final public const VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL = 'customer_language_not_in_sales_channel';

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $customerRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();

        // Skip validation for Store API requests, as this is already handled by the storefront and avoids unnecessary performance overhead.
        if ($context->getSource() instanceof SalesChannelApiSource) {
            return;
        }

        $candidateCommands = [];
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
                continue;
            }

            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $extractedPayloads = $this->extractPayloads($command);
            if ($extractedPayloads) {
                $candidateCommands[] = $extractedPayloads;
            }
        }

        if ($candidateCommands === []) {
            return;
        }

        $customerPks = $event->getPrimaryKeys(CustomerDefinition::ENTITY_NAME);
        $customersSalesChannels = $this->loadCustomersSalesChannels($customerPks, $context);
        $salesChannels = $this->loadSalesChannels($customersSalesChannels, $candidateCommands, $context);

        foreach ($candidateCommands as $candidate) {
            $customerId = $candidate['customerId'];
            $languageId = $candidate['languageId'];
            $salesChannelId = $candidate['salesChannelId'] ?? $customersSalesChannels[$customerId] ?? null;

            if ($languageId === null || $salesChannelId === null) {
                continue;
            }

            if ($this->isLanguageInSalesChannel($salesChannelId, $languageId, $salesChannels)) {
                continue;
            }

            $event->getExceptions()->add(
                $this->createLanguageNotInSalesChannelViolation($languageId, $candidate['path'])
            );
        }
    }

    /**
     * @return array{customerId: string|null, languageId: string|null, salesChannelId: string|null, path: string}|null
     */
    private function extractPayloads(WriteCommand|UpdateCommand $command): ?array
    {
        $payload = $command->getPayload();

        $languageId = isset($payload['language_id'])
            ? Uuid::fromBytesToHex($payload['language_id'])
            : null;

        if ($languageId === null) {
            return null;
        }

        $customerId = isset($command->getPrimaryKey()['id'])
            ? Uuid::fromBytesToHex($command->getPrimaryKey()['id'])
            : null;
        $salesChannelId = isset($payload['sales_channel_id'])
            ? Uuid::fromBytesToHex($payload['sales_channel_id'])
            : null;

        return [
            'customerId' => $customerId,
            'languageId' => $languageId,
            'salesChannelId' => $salesChannelId,
            'path' => $command->getPath(),
        ];
    }

    /**
     * @param list<array<string, string>> $customerPks
     *
     * @return array<string>
     */
    private function loadCustomersSalesChannels(array $customerPks, Context $context): array
    {
        $customerIds = array_values(array_filter(array_map(
            fn (array $pk) => isset($pk['id']) ? Uuid::fromBytesToHex($pk['id']) : null,
            $customerPks
        )));
        if ($customerIds === []) {
            return [];
        }

        $criteria = new Criteria($customerIds);
        /** @var CustomerCollection $customers */
        $customers = $this->customerRepository->search($criteria, $context)->getEntities();

        return $customers->getSalesChannelIds();
    }

    /**
     * @param array<string> $customersSalesChannels
     * @param list<array{customerId: string|null, languageId: string|null, salesChannelId: string|null, path: string}> $candidateCommands
     *
     * @return EntityCollection<SalesChannelEntity>
     */
    private function loadSalesChannels(?array $customersSalesChannels, array $candidateCommands, Context $context): EntityCollection
    {
        $customersSalesChannelsIds = array_values($customersSalesChannels ?? []);
        $salesChannelIds = array_map(
            fn (array $candidate) => $candidate['salesChannelId'],
            $candidateCommands
        );
        $languageIds = array_map(
            fn (array $candidate) => $candidate['languageId'],
            $candidateCommands
        );

        $ids = array_values(array_unique(array_filter([
            ...$customersSalesChannelsIds,
            ...$salesChannelIds,
        ])));
        if ($ids === []) {
            return new EntityCollection();
        }

        $criteria = new Criteria($ids);
        $criteria->addFields(['id', 'languages.id']);

        $association = $criteria->getAssociation('languages');
        $association->addFilter(new EqualsAnyFilter('id', $languageIds));

        return $this->salesChannelRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param EntityCollection<SalesChannelEntity> $salesChannels
     */
    private function isLanguageInSalesChannel(string $salesChannelId, string $languageId, EntityCollection $salesChannels): bool
    {
        $salesChannel = $salesChannels->get($salesChannelId);

        /** @var LanguageCollection|null $languages */
        $languages = $salesChannel?->get('languages');

        return $languages?->has($languageId) ?? false;
    }

    private function createLanguageNotInSalesChannelViolation(string $languageId, string $path): WriteConstraintViolationException
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            \sprintf('The language "%s" is not assigned to the sales channel.', $languageId),
            'The language "{{ languageId }}" is not assigned to the sales channel.',
            ['{{ languageId }}' => $languageId],
            $path,
            '/languageId',
            $languageId,
            null,
            self::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL
        ));

        return new WriteConstraintViolationException($violations, $path);
    }
}
