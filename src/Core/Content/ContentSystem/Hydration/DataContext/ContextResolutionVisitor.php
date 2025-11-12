<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\Log\Package;

/**
 * Visitor implementing direct-children-only context distribution.
 *
 * @internal
 */
#[Package('discovery')]
class ContextResolutionVisitor implements ElementVisitor
{
    /**
     * @param iterable<DistributionStrategyInterface> $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
        private readonly ContextPathResolver $pathResolver
    ) {
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
    }

    public function leave(ContentElement $element): void
    {
        // No-op: direct-children-only distribution requires no cleanup
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
        $distributionConfig = $providerElement->getProvidesContext()[$contextKey]->getDistribution();
        $consumerKey = $distributionConfig->getConsumerAlias() ?? $contextKey;

        $consumers = $providerElement->collectConsumers($consumerKey);

        if ($consumers === []) {
            return;
        }

        $strategy = $this->findStrategy($distribution);

        if ($strategy === null) {
            foreach ($consumers as $consumer) {
                $this->setContextForConsumer($consumer, $consumerKey, $data);
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
                $this->setContextForConsumer($consumer, $consumerKey, $distributed[$index]);
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

    private function setContextForConsumer(ContentElement $consumer, string $providerKey, mixed $data): void
    {
        $acceptedContexts = $consumer->getAcceptsContext();

        foreach ($acceptedContexts as $consumerKey => $consumerDef) {
            if (!ContextPathResolver::matches($providerKey, $consumerKey)) {
                continue;
            }

            $propertyKey = $consumerDef->propertyAlias ?? $consumerKey;

            if ($consumerKey === $providerKey) {
                $consumer->setProperty($propertyKey, $data);
                continue;
            }

            $path = ContextPathResolver::parseContextKey($consumerKey);

            $resolvedValue = $this->pathResolver->resolvePath(
                $data,
                $path,
                $consumerDef->required,
                $consumerKey,
                $consumer->getId()
            );

            $consumer->setProperty($propertyKey, $resolvedValue);
        }
    }
}
