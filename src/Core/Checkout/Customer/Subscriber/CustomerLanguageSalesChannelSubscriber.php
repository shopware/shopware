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

        // Skip validation for SalesChannel API requests to avoids unnecessary performance overhead
        if ($context->getSource() instanceof SalesChannelApiSource) {
            return;
        }

        $candidates = $this->collectCandidatesCommands($event);
        if ($candidates === []) {
            return;
        }

        $customerSalesChannelMap = $this->getCustomersSalesChannelsIds($candidates, $context);
        $salesChannels = $this->loadSalesChannels($customerSalesChannelMap, $candidates, $context);

        foreach ($candidates as $candidate) {
            $salesChannelId = $this->resolveSalesChannelId($candidate, $customerSalesChannelMap);
            if ($salesChannelId === null) {
                continue;
            }

            if ($this->isLanguageInSalesChannel($salesChannelId, $candidate['languageId'], $salesChannels)) {
                continue;
            }

            $event->getExceptions()->add(
                $this->createLanguageNotInSalesChannelViolation($candidate['languageId'], $candidate['customerId'])
            );
        }
    }

    /**
     * @return list<array{customerId: string|null, languageId: string, salesChannelId: string|null}>
     */
    private function collectCandidatesCommands(PreWriteValidationEvent $event): array
    {
        $candidates = [];

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
                continue;
            }

            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $candidate = $this->extractCandidatePayloads($command);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * @param array{customerId: string|null, languageId: string, salesChannelId: string|null} $candidate
     * @param array<string, string> $customerSalesChannelMap
     */
    private function resolveSalesChannelId(array $candidate, array $customerSalesChannelMap): ?string
    {
        if ($candidate['salesChannelId'] !== null) {
            return $candidate['salesChannelId'];
        }

        $customerId = $candidate['customerId'];
        if ($customerId === null) {
            return null;
        }

        return $customerSalesChannelMap[$customerId] ?? null;
    }

    /**
     * @return array{customerId: string|null, languageId: string, salesChannelId: string|null}|null
     */
    private function extractCandidatePayloads(InsertCommand|UpdateCommand $command): ?array
    {
        $payload = $command->getPayload();
        if (!isset($payload['language_id'])) {
            return null;
        }

        $pk = $command->getPrimaryKey();

        return [
            'customerId' => $command instanceof UpdateCommand && isset($pk['id']) ? Uuid::fromBytesToHex($pk['id']) : null,
            'languageId' => Uuid::fromBytesToHex($payload['language_id']),
            'salesChannelId' => isset($payload['sales_channel_id']) ? Uuid::fromBytesToHex($payload['sales_channel_id']) : null,
        ];
    }

    /**
     * @param list<array{customerId: string|null, languageId: string, salesChannelId: string|null}> $candidates
     *
     * @return array<string, string>
     */
    private function getCustomersSalesChannelsIds(array $candidates, Context $context): array
    {
        $customerIds = \array_values(\array_filter(\array_map(
            static fn (array $candidate) => $candidate['customerId'],
            $candidates
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
     * @param array<string, string> $customerSalesChannelMap
     * @param list<array{customerId: string|null, languageId: string, salesChannelId: string|null}> $candidates
     *
     * @return EntityCollection<SalesChannelEntity>
     */
    private function loadSalesChannels(array $customerSalesChannelMap, array $candidates, Context $context): EntityCollection
    {
        $customerSalesChannelIds = \array_values($customerSalesChannelMap);

        $explicitSalesChannelIds = \array_values(\array_filter(\array_map(
            static fn (array $candidate) => $candidate['salesChannelId'],
            $candidates
        )));

        $languageIds = \array_values(\array_unique(\array_map(
            static fn (array $candidate) => $candidate['languageId'],
            $candidates
        )));

        $salesChannelIds = \array_values(\array_unique(\array_filter([
            ...$customerSalesChannelIds,
            ...$explicitSalesChannelIds,
        ])));

        if ($salesChannelIds === []) {
            return new EntityCollection();
        }

        $criteria = new Criteria($salesChannelIds);
        $criteria->addFields(['id', 'languages.id']);
        $criteria->getAssociation('languages')
            ->addFilter(new EqualsAnyFilter('id', $languageIds));

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

    private function createLanguageNotInSalesChannelViolation(string $languageId, ?string $customerId): WriteConstraintViolationException
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            \sprintf('The language "%s" is not assigned to the sales channel.', $languageId),
            'The language "{{ languageId }}" is not assigned to the sales channel.',
            ['{{ languageId }}' => $languageId],
            null,
            null,
            $languageId,
            null,
            self::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL
        ));

        return new WriteConstraintViolationException($violations, $customerId ?? $languageId);
    }
}
