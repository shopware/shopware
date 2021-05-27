<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\WebhookAware;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class AddTagAction extends FlowAction
{
    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    public function __construct(DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
    }

    public function getName(): string
    {
        return FlowAction::ADD_TAG;
    }

    public static function getSubscribedEvents()
    {
        return [
            FlowAction::ADD_TAG => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class, CustomerAware::class, WebhookAware::class, SalesChannelAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $config = $event->getConfig();
        if (!\array_key_exists('entity', $config)) {
            return;
        }

        $baseEvent = $event->getEvent();
        $entity = $config['entity'];
        $entityRepository = $this->definitionInstanceRegistry->getRepository($entity);
        if ($baseEvent instanceof OrderAware && $entity === OrderDefinition::ENTITY_NAME) {
            $entityRepository->update([
                [
                    'id' => $baseEvent->getOrderId(),
                    'tags' => [
                        ['id' => $config['tagId']],
                    ],
                ],
            ], $baseEvent->getContext());
        }
    }
}
