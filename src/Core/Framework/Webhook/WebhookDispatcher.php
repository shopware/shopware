<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class WebhookDispatcher implements EventDispatcherInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly WebhookManager $webhookManager,
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        // @deprecated tag:v6.7.0 - remove DISABLE_EXTENSIONS from if condition
        if (EnvironmentHelper::getVariable('DISABLE_EXTENSIONS', false) || !HookableEventFactory::isHookable($event)) {
            return $event;
        }

        $this->webhookManager->dispatch($event);

        return $event;
    }

    /**
     * @param callable $listener can not use native type declaration @see https://github.com/symfony/symfony/issues/42283
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void // @phpstan-ignore-line
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    /**
     * @return array<array-key, array<array-key, callable(object): void>|callable(object): void>
     */
    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }
}
