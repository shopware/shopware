<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\ElementVisitor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Visitor implementing direct-children-only context distribution.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ContextResolutionVisitor implements ElementVisitor
{
    public function __construct(
        private readonly ContextPathResolver $pathResolver
    ) {
    }

    public function enter(ContentElement $element): void
    {
        $providesContext = $element->getProvidesContext();

        if ($providesContext !== []) {
            foreach ($providesContext as $contextKey => $contextProvider) {
                $data = $element->getProperty($contextKey);

                if ($data !== null) {
                    $this->distributeContextToChildren(
                        $element,
                        $contextKey,
                        $data,
                        $contextProvider->distributionConfig
                    );
                }
            }
        }
    }

    public function leave(ContentElement $element): void
    {
        // No-op: direct-children-only distribution requires no cleanup
    }

    private function distributeContextToChildren(
        ContentElement $providerElement,
        string $contextKey,
        mixed $data,
        DistributionConfig $config
    ): void {
        $consumerKey = $config->getConsumerAlias() ?? $contextKey;

        $consumers = $providerElement->collectConsumers($consumerKey, $this->pathResolver);

        if ($consumers === []) {
            return;
        }

        $consumerData = array_values(array_map(fn (ContentElement $element) => [
            'component' => $element->getComponent(),
            'properties' => $element->getProperties(),
        ], $consumers));

        $distributed = $config->distribute($data, $consumerData);

        foreach ($consumers as $index => $consumer) {
            if (\array_key_exists($index, $distributed)) {
                $this->setContextForConsumer($consumer, $consumerKey, $distributed[$index]);
            }
        }
    }

    private function setContextForConsumer(ContentElement $consumer, string $providerKey, mixed $data): void
    {
        $acceptedContexts = $consumer->getAcceptsContext();

        foreach ($acceptedContexts as $consumerKey => $consumerDef) {
            if (!$this->pathResolver->matches($providerKey, $consumerKey)) {
                continue;
            }

            $propertyKey = $consumerDef->propertyAlias ?? $consumerKey;

            if ($consumerKey === $providerKey) {
                $consumer->setProperty($propertyKey, $data);
                continue;
            }

            if (!$data instanceof Struct) {
                if ($consumerDef->required) {
                    throw ContentSystemException::contextPathNotResolvable(
                        $consumerKey,
                        $consumer->getId(),
                        'Context data is not a Struct instance'
                    );
                }
                $consumer->setProperty($propertyKey, null);
                continue;
            }

            $path = $this->pathResolver->parseContextKey($consumerKey);

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
