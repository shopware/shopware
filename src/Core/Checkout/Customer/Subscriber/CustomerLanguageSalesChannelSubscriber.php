<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
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
     * @param EntityRepository<EntityCollection<PartialEntity>> $salesChannelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
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

        $salesChannels = $this->fetchSalesChannels($candidates, $context);

        foreach ($candidates as $candidate) {
            $salesChannelId = $this->findSalesChannelIdForCustomer($candidate, $salesChannels);
            if ($salesChannelId === null) {
                continue;
            }

            if ($this->isLanguageInSalesChannel($salesChannelId, $candidate['languageId'], $salesChannels)) {
                continue;
            }

            $event->getExceptions()->add(
                $this->createLanguageNotInSalesChannelViolation($candidate['languageId'])
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

            $payload = $command->getPayload();
            if (!isset($payload['language_id'])) {
                continue;
            }

            $pk = $command->getPrimaryKey();

            $candidates[] = [
                'customerId' => $command instanceof UpdateCommand && isset($pk['id']) ? Uuid::fromBytesToHex($pk['id']) : null,
                'languageId' => Uuid::fromBytesToHex($payload['language_id']),
                'salesChannelId' => isset($payload['sales_channel_id']) ? Uuid::fromBytesToHex($payload['sales_channel_id']) : null,
            ];
        }

        return $candidates;
    }

    /**
     * @param array{customerId: string|null, languageId: string, salesChannelId: string|null} $candidate
     * @param EntityCollection<PartialEntity> $salesChannels
     */
    private function findSalesChannelIdForCustomer(array $candidate, EntityCollection $salesChannels): ?string
    {
        if ($candidate['salesChannelId'] !== null) {
            return $candidate['salesChannelId'];
        }

        $customerId = $candidate['customerId'];
        if ($customerId === null) {
            return null;
        }

        foreach ($salesChannels as $salesChannel) {
            /** @var EntityCollection<PartialEntity>|null $customers */
            $customers = $salesChannel->get('customers');
            if ($customers?->has($customerId)) {
                return $salesChannel->getId();
            }
        }

        return null;
    }

    /**
     * @param list<array{customerId: string|null, languageId: string, salesChannelId: string|null}> $candidates
     *
     * @return EntityCollection<PartialEntity>
     */
    private function fetchSalesChannels(array $candidates, Context $context): EntityCollection
    {
        $customerIds = \array_filter(\array_column($candidates, 'customerId'));
        $salesChannelIds = \array_filter(\array_column($candidates, 'salesChannelId'));

        if ($customerIds === [] && $salesChannelIds === []) {
            return new EntityCollection();
        }

        $criteria = (new Criteria())->addFields(['id', 'languages.id'])
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsAnyFilter('id', $salesChannelIds),
                new EqualsAnyFilter('customers.id', $customerIds),
            ]));

        $criteria->getAssociation('languages')
            ->addFilter(new EqualsAnyFilter('id', \array_column($candidates, 'languageId')));

        if ($customerIds !== []) {
            $criteria->addFields(['customers.id']);
            $criteria->getAssociation('customers')
                ->addFilter(new EqualsAnyFilter('id', $customerIds));
        }

        return $this->salesChannelRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param EntityCollection<PartialEntity> $salesChannels
     */
    private function isLanguageInSalesChannel(string $salesChannelId, string $languageId, EntityCollection $salesChannels): bool
    {
        $salesChannel = $salesChannels->get($salesChannelId);

        /** @var EntityCollection<PartialEntity>|null $languages */
        $languages = $salesChannel?->get('languages');

        return $languages?->has($languageId) ?? false;
    }

    private function createLanguageNotInSalesChannelViolation(string $languageId): WriteConstraintViolationException
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            \sprintf('The language "%s" is not assigned to the sales channel.', $languageId),
            'The language "{{ languageId }}" is not assigned to the sales channel.',
            ['{{ languageId }}' => $languageId],
            '',
            '/languageId',
            $languageId,
            null,
            self::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL
        ));

        return new WriteConstraintViolationException($violations);
    }
}
