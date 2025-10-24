<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\Log\Package;

/**
 * Visitor implementing context resolution with provider shadowing.
 *
 * @internal
 */
#[Package('discovery')]
class ContextResolutionVisitor implements ElementVisitor
{
    private readonly DataContextStack $stack;

    /**
     * @param iterable<DistributionStrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
    ) {
        $this->stack = new DataContextStack();
    }

    public function enter(ContentElement $element): void
    {
        $providesContext = $element->getProvidesContext();

        if ($providesContext !== []) {
            foreach ($providesContext as $contextKey => $providerDef) {
                $data = $element->getProperty($contextKey);
                $distributionConfig = $providerDef->getDistribution();
                $distribution = $distributionConfig->getStrategy()->value;

                if ($data !== null) {
                    $this->stack->push($contextKey, $data, $distribution);

                    $this->distributeContextToChildren(
                        $element,
                        $contextKey,
                        $data,
                        $distribution,
                        $distributionConfig->toArray()
                    );
                }
            }
        }

        $acceptsContext = $element->getAcceptsContext();
        if ($acceptsContext !== []) {
            foreach ($acceptsContext as $contextKey => $consumerDef) {
                if (!$element->hasProperty($contextKey) && $this->stack->has($contextKey)) {
                    $contextData = $this->stack->get($contextKey);
                    if ($contextData !== null) {
                        $element->setProperty($contextKey, $contextData->data);
                    }
                }
            }
        }
    }

    public function leave(ContentElement $element): void
    {
        $providesContext = $element->getProvidesContext();

        if ($providesContext !== []) {
            foreach ($providesContext as $contextKey => $providerDef) {
                $this->stack->pop($contextKey);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function distributeContextToChildren(
        ContentElement $providerElement,
        string $contextKey,
        mixed $data,
        string $distribution,
        array $config
    ): void {
        $consumers = $providerElement->collectConsumers($contextKey);

        if ($consumers === []) {
            return;
        }

        $strategy = $this->findStrategy($distribution);

        if ($strategy === null) {
            foreach ($consumers as $consumer) {
                $consumer->setProperty($contextKey, $data);
            }

            return;
        }

        $consumerData = array_map(fn (ContentElement $el) => [
            'type' => $el->getType(),
            'data_key' => $el->getProperties()['data_key'] ?? null,
        ], $consumers);

        $distributed = $strategy->distribute($data, $consumerData, $config);

        foreach ($consumers as $index => $consumer) {
            if (isset($distributed[$index])) {
                $consumer->setProperty($contextKey, $distributed[$index]);
            }
        }
    }

    private function findStrategy(string $distribution): ?DistributionStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($distribution)) {
                return $strategy;
            }
        }

        return null;
    }
}
