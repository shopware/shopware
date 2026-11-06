<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * The one owner of an element's child-facing delivery keys: which key each of the element's providers
 * delivers to its direct children under, and the rejection of two producers that share one.
 *
 * The child-facing key is the key the distributor matches children on
 * ({@see \Shopware\Core\Framework\ContentSystem\Rendering\ContextDistributor::distribute()}:
 * `distributionConfig->getConsumerAlias() ?? providerKey`). Two producers sharing it both deliver to the
 * same children and the later one silently wins by iteration order, so the layout is rejected instead.
 *
 * The judged set is the element's full delivery surface: the authored providers plus the broadcast
 * providers the redistribute derivation will add from `redistribute` consumers, whose child-facing key is
 * `consumerAlias ?? contextKey`. That formula lives here once, in {@see derivedChildKey()}, and the
 * derivation itself calls it, so the check cannot drift from the delivery it gates.
 *
 * Both enforcement sites route through this class — the serving path
 * ({@see \Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner}, over the pre-prune forest) and
 * the diagnostics context walk
 * ({@see \Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver}, over the target and
 * its ancestors) — so neither can drift from the other. Which elements each site judges stays that site's
 * own decision.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ProviderDeliveryKeyResolver
{
    /**
     * The child-facing key of every provider the element delivers under.
     *
     * Authored providers are indexed first and the derived broadcast providers second; that order decides
     * which of two colliding producers is reported as `first`.
     *
     * Keys are cast to string on both the map and the exception arguments: {@see ContextDefinitions} carries
     * no runtime key guard, so PHP's numeric-string key coercion can hand this method an `int` key, while
     * {@see ContentSystemException::providerDeliveryCollision()} is string-typed under `strict_types`.
     *
     * @throws ContentSystemException PROVIDER_DELIVERY_COLLISION when two of the element's child-facing key
     *                                producers — two authored providers, an authored provider and a
     *                                redistribute consumer, or two redistribute consumers — deliver under
     *                                the same key
     *
     * @return array<string, string> child-facing key => the provider key or consumer context key that delivers under it
     */
    public function resolve(ContextDefinitions $definitions, string $elementId): array
    {
        $childKeys = [];

        foreach ($definitions->getAllProviders() as $providerKey => $provider) {
            $childKey = $provider->distributionConfig->getConsumerAlias() ?? (string) $providerKey;

            if (\array_key_exists($childKey, $childKeys)) {
                throw ContentSystemException::providerDeliveryCollision($childKey, $childKeys[$childKey], (string) $providerKey, $elementId);
            }

            $childKeys[$childKey] = (string) $providerKey;
        }

        foreach ($definitions->getAllConsumers() as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            $childKey = $this->derivedChildKey($consumer, (string) $contextKey);

            if (\array_key_exists($childKey, $childKeys)) {
                throw ContentSystemException::providerDeliveryCollision($childKey, $childKeys[$childKey], (string) $contextKey, $elementId);
            }

            $childKeys[$childKey] = (string) $contextKey;
        }

        return $childKeys;
    }

    /**
     * The key a redistribute consumer's derived broadcast provider delivers children under.
     *
     * The single definition shared by the redistribute derivation and this class's own validation: the
     * derivation configures the virtual provider's broadcast config with this key and {@see resolve()}
     * judges collisions on it, so both read the same formula.
     */
    public function derivedChildKey(ContextConsumer $consumer, string $contextKey): string
    {
        return $consumer->consumerAlias ?? $contextKey;
    }
}
