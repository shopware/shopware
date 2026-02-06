<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('after-sales')]
readonly class FlowFactory
{
    /**
     * @param iterable<FlowStorer> $storer
     */
    public function __construct(private iterable $storer)
    {
    }

    /**
     * Extracts event data and immediately restores it into a {@see StorableFlow}
     */
    public function create(FlowEventAware $event): StorableFlow
    {
        $stored = $this->getStored($event);

        return $this->restore($event->getName(), $event->getContext(), $stored);
    }

    public function createBuffered(FlowEventAware $event): BufferedFlow
    {
        $stored = $this->getStored($event);

        return new BufferedFlow($event->getName(), $event->getContext(), $stored);
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $data
     */
    public function restore(string $name, Context $context, array $stored = [], array $data = []): StorableFlow
    {
        $systemContext = new Context(
            new SystemSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyFactor(),
            $context->considerInheritance(),
            $context->getTaxState(),
            $context->getRounding(),
        );
        $systemContext->setExtensions($context->getExtensions());

        $flow = new StorableFlow($name, $systemContext, $stored, $data);

        foreach ($this->storer as $storer) {
            $storer->restore($flow);
        }

        return $flow;
    }

    public function restoreBuffered(BufferedFlow $bufferedFlow): StorableFlow
    {
        return $this->restore($bufferedFlow->eventName, $bufferedFlow->eventContext, $bufferedFlow->stored);
    }

    /**
     * @return array<string, mixed>
     */
    private function getStored(FlowEventAware $event): array
    {
        $stored = [];
        foreach ($this->storer as $storer) {
            $stored = $storer->store($event, $stored);
        }

        return $stored;
    }
}
