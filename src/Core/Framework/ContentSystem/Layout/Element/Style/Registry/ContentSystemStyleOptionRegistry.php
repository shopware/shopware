<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\AbstractContentSystemStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemStyleOptionRegistry extends AbstractContentSystemStyleOptionRegistry
{
    /**
     * @internal
     *
     * @param iterable<AbstractContentSystemStyleOptionLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    public function getDecorated(): AbstractContentSystemStyleOptionRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    public function all(): array
    {
        $options = [];

        // Cross-loader dedup: individual loaders guarantee internal uniqueness, this catches collisions across loaders
        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $specification) {
                $name = $specification->name();

                if (\array_key_exists($name, $options)) {
                    throw ContentSystemException::styleOptionDuplicate(
                        $name,
                        $options[$name]->source(),
                        $specification->source(),
                    );
                }

                $options[$name] = $specification;
            }
        }

        return $options;
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    public function allResolved(): array
    {
        $options = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $specification) {
                $name = $specification->name();
                $existing = $options[$name] ?? null;

                $options[$name] = $existing === null
                    ? $specification
                    : $this->resolveCollision($existing, $specification);
            }
        }

        return $options;
    }

    private function resolveCollision(
        StyleOptionSpecification $existing,
        StyleOptionSpecification $candidate,
    ): StyleOptionSpecification {
        // A lower tier wins; within the same tier the first-registered option (the existing one) wins.
        // A cross-loader collision is surfaced loudly by the strict all() (it throws with both source labels
        // on every write and install), so the lenient read resolves silently rather than logging from inside
        // the cached view, where the warning would be gated by the cache.
        return $this->sourceTier($candidate->source()) < $this->sourceTier($existing->source())
            ? $candidate
            : $existing;
    }

    /**
     * Source precedence for resolving a cross-loader duplicate: lower wins. An unrecognized source label
     * has the lowest precedence, so a known source always wins.
     */
    private function sourceTier(string $source): int
    {
        return match (true) {
            $source === 'core' => 0,
            str_starts_with($source, 'bundle:') => 1,
            str_starts_with($source, 'plugin:') => 2,
            str_starts_with($source, 'app:') => 3,
            default => 4,
        };
    }
}
