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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerLanguageSalesChannelSubscriberTest
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
        private readonly WriteOutcome $writeOutcome,
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

        $candidates = $this->collectCandidates($event);
        if ($candidates === []) {
            return;
        }

        $salesChannels = $this->fetchSalesChannels($candidates, $context);

        foreach ($candidates as $candidate) {
            if ($this->isLanguageInSalesChannel($candidate['salesChannelId'], $candidate['languageId'], $salesChannels)) {
                continue;
            }

            $event->getExceptions()->add(
                $this->createLanguageNotInSalesChannelViolation($candidate['languageId'])
            );
        }
    }

    /**
     * @return list<array{languageId: string, salesChannelId: string}> each customer's language and sales channel after the write
     */
    private function collectCandidates(PreWriteValidationEvent $event): array
    {
        $customers = $this->writeOutcome->resultingRows(
            $event->getCommandsForEntity(CustomerDefinition::ENTITY_NAME),
            ['language_id', 'sales_channel_id']
        );

        $candidates = [];
        foreach ($customers as $customer) {
            $languageId = $customer->getResultingValue('language_id');
            $salesChannelId = $customer->getResultingValue('sales_channel_id');
            if (!\is_string($languageId) || !\is_string($salesChannelId)) {
                continue;
            }

            $candidates[] = [
                'languageId' => Uuid::fromBytesToHex($languageId),
                'salesChannelId' => Uuid::fromBytesToHex($salesChannelId),
            ];
        }

        return $candidates;
    }

    /**
     * @param list<array{languageId: string, salesChannelId: string}> $candidates
     *
     * @return EntityCollection<PartialEntity>
     */
    private function fetchSalesChannels(array $candidates, Context $context): EntityCollection
    {
        $criteria = (new Criteria())->addFields(['id', 'languages.id'])
            ->addFilter(new EqualsAnyFilter('id', array_column($candidates, 'salesChannelId')));

        $criteria->getAssociation('languages')
            ->addFilter(new EqualsAnyFilter('id', array_column($candidates, 'languageId')));

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
