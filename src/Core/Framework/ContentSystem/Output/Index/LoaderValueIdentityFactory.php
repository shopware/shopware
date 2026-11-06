<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Index;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * Mints the {@see LoaderValueIdentity} for one resolved data requirement, at the moment its loader returns.
 *
 * It has to run here rather than at indexing time, because two of the four components exist only here: the
 * resolved inputs are gone by then, and `producedFingerprint` must describe the value the LOADER returned
 * rather than the value the response ends up carrying — that difference is the whole mechanism for telling a
 * finalization listener's replacement from genuine loader output.
 *
 * THE TWO HASHES ARE CANONICALIZED DIFFERENTLY, deliberately:
 *
 * - the config hash runs through {@see ConfigCanonicalizer}, which value-sorts lists, because a config's list
 *   order is authoring noise: two elements configuring the same loader with the same ids in a different order
 *   are the same load.
 * - the inputs hash normalizes MAP KEY ORDER ONLY and preserves list order, because a resolved input's list
 *   order can be meaning. An ordered id list handed to a listing loader produces a different result in a
 *   different order, so value-sorting it would merge two loads that are not the same load.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LoaderValueIdentityFactory
{
    public function __construct(
        private DataLoaderConfigSerializerProvider $configSerializerProvider,
        private ConfigCanonicalizer $configCanonicalizer,
        private ValueFingerprinter $fingerprinter,
    ) {
    }

    public function create(DataRequirement $requirement, LoaderInputs $inputs, mixed $value): LoaderValueIdentity
    {
        return new LoaderValueIdentity(
            $requirement->source,
            $this->hashConfig($requirement),
            $this->hashInputs($inputs),
            $this->fingerprinter->fingerprint($value),
        );
    }

    private function hashConfig(DataRequirement $requirement): string
    {
        $config = $this->configSerializerProvider->encode($requirement->source, $requirement->config);

        return Hasher::hash($this->configCanonicalizer->canonicalize($config));
    }

    private function hashInputs(LoaderInputs $inputs): string
    {
        return Hasher::hash($this->normalizeKeyOrder($inputs->all()));
    }

    /**
     * Sorts map keys at every depth and leaves lists exactly as they are, so two resolutions differing only in
     * the order their keys happen to be written hash alike while two differing in a list's order do not.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function normalizeKeyOrder(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[$key] = \is_array($value) ? $this->normalizeKeyOrder($value) : $value;
        }

        if (!array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }
}
