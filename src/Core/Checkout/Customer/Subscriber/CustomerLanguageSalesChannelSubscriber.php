<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
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
        if ($context->getSource() instanceof SalesChannelApiSource) {
            return;
        }

        $salesChannels = $this->loadSalesChannels($context);

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== CustomerDefinition::ENTITY_NAME || !$command instanceof InsertCommand) {
                continue;
            }

            $payload = $command->getPayload();
            $languageId = isset($payload['language_id']) && $payload['language_id'] !== ''
                ? Uuid::fromBytesToHex($payload['language_id'])
                : null;
            $salesChannelId = isset($payload['sales_channel_id']) && $payload['sales_channel_id'] !== ''
                ? Uuid::fromBytesToHex($payload['sales_channel_id'])
                : null;

            if ($languageId === null || $salesChannelId === null) {
                continue;
            }

            if ($this->isLanguageInSalesChannel($salesChannelId, $languageId, $salesChannels)) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $violations->add(new ConstraintViolation(
                \sprintf('The language "%s" is not assigned to the sales channel.', $languageId),
                'The language "{{ languageId }}" is not assigned to the sales channel.',
                ['{{ languageId }}' => $languageId],
                $command->getPath(),
                '/languageId',
                $languageId,
                null,
                self::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL
            ));

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }

    private function loadSalesChannels(Context $context): SalesChannelCollection
    {
        $criteria = new Criteria();

        $association = $criteria->getAssociation('languages');
        $association->addFields(['id']);

        return $this->salesChannelRepository->search($criteria, $context)->getEntities();
    }

    private function isLanguageInSalesChannel(string $salesChannelId, string $languageId, SalesChannelCollection $salesChannels): bool
    {
        $salesChannel = $salesChannels->get($salesChannelId);

        return $salesChannel
            ?->getLanguages()
            ?->has($languageId)
            ?? false;
    }
}
