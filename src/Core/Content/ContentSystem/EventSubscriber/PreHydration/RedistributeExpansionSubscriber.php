<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\EventSubscriber\PreHydration;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Expands redistribute flags on consumers into broadcast providers.
 *
 * Consumers with `redistribute: true` automatically provide their received context
 * to descendants. This subscriber generates the ContextProvider objects that enable
 * this behavior during rendering.
 *
 * @internal
 */
#[Package('discovery')]
class RedistributeExpansionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreContentHydrationEvent::class => ['onPreContentHydration', 4000],
        ];
    }

    public function onPreContentHydration(PreContentHydrationEvent $event): void
    {
        foreach ($event->elements as $element) {
            $this->expandRecursively($element);
        }
    }

    private function expandRecursively(ContentElement $element): void
    {
        $consumers = $element->getAcceptsContext();
        $providers = $element->getProvidesContext();

        $this->validatePropertyAliases($consumers);
        $virtualProviders = $this->generateVirtualProviders($consumers, $providers);

        if ($virtualProviders !== []) {
            $newDefinitions = $element->getContextDefinitions()->withAddedProviders($virtualProviders);
            $element->setContextDefinitions($newDefinitions);
        }

        foreach ($element->allSlotElements() as $child) {
            $this->expandRecursively($child);
        }
    }

    /**
     * Generates virtual providers from consumers with redistribute flag.
     *
     * @param array<string, ContextConsumer> $consumers
     * @param array<string, ContextProvider> $existingProviders
     *
     * @return array<string, ContextProvider>
     */
    private function generateVirtualProviders(array $consumers, array $existingProviders): array
    {
        $virtualProviders = [];

        foreach ($consumers as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            if (str_contains($contextKey, '.')) {
                throw ContentSystemException::redistributeWithDottedPath($contextKey);
            }

            $providerKey = $consumer->consumerAlias ?? $contextKey;

            if (\array_key_exists($providerKey, $existingProviders)) {
                throw ContentSystemException::redistributeConflict($contextKey);
            }

            $virtualProviders[$providerKey] = new ContextProvider(
                $consumer->type,
                BroadcastDistributionConfig::simple()
            );
        }

        return $virtualProviders;
    }

    /**
     * Validates property alias uniqueness within an element.
     *
     * @param array<string, ContextConsumer> $consumers
     */
    private function validatePropertyAliases(array $consumers): void
    {
        $propertyKeys = [];

        foreach ($consumers as $contextKey => $consumer) {
            $propertyKey = $consumer->propertyAlias ?? $contextKey;

            $baseKey = str_contains($propertyKey, '.')
                ? substr($propertyKey, 0, (int) strpos($propertyKey, '.'))
                : $propertyKey;

            if (\array_key_exists($baseKey, $propertyKeys)) {
                throw ContentSystemException::propertyAliasCollision(
                    $baseKey,
                    $propertyKeys[$baseKey],
                    $contextKey
                );
            }

            $propertyKeys[$baseKey] = $contextKey;
        }
    }
}
