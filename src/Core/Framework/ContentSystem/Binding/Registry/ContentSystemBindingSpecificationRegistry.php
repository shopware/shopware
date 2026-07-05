<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Registry;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\AbstractContentSystemBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\DatabaseBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('framework')]
class ContentSystemBindingSpecificationRegistry extends AbstractContentSystemBindingSpecificationRegistry
{
    // Loader-origin ranks for the promoted-uniqueness tiebreak: an authored (YAML) flag beats a persisted (DB)
    // one. Ordered so the numerically smaller rank wins, matching the lexicographic id tiebreak within a rank.
    private const ORIGIN_AUTHORED = 0;
    private const ORIGIN_DATABASE = 1;

    /**
     * @internal
     *
     * @param iterable<AbstractContentSystemBindingSpecificationLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractContentSystemBindingSpecificationRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, BindingSpecification>
     */
    public function all(): array
    {
        $specifications = [];
        $origins = [];

        foreach ($this->loaders as $loader) {
            // Loader origin drives the promoted-uniqueness tiebreak in demoteExcessPromoted(). Classified by loader
            // class, not a loader source name, so no loader source name is tested.
            $origin = $loader instanceof DatabaseBindingSpecificationLoader ? self::ORIGIN_DATABASE : self::ORIGIN_AUTHORED;

            foreach ($loader->load() as $specification) {
                $qualifiedId = $specification->qualifiedId();

                if (isset($specifications[$qualifiedId])) {
                    throw ContentSystemException::bindingSpecificationDuplicate(
                        $specification->id(),
                        $specifications[$qualifiedId]->source(),
                        $specification->source(),
                    );
                }

                $specifications[$qualifiedId] = $specification;
                $origins[$qualifiedId] = $origin;
            }
        }

        return $this->demoteExcessPromoted($specifications, $origins);
    }

    /**
     * The aggregation backstop for the promoted-uniqueness invariant. The YAML loader hard-throws within its
     * own set and the app validator soft-rejects, but ordinary install-then-activate ordering can still let more
     * than one promoted specification for a type reach the merge (app-vs-YAML or app-vs-app). Keep one deterministic
     * winner and demote the rest in the aggregated result, logging a `warning` per demotion, the same drop-and-warn
     * resilience the DB loader applies to poison rows, rather than a hard throw. The cached decorator caches this
     * already-demoted aggregate, so no further reconciliation happens downstream.
     *
     * Winner rule: an authored (YAML) flag beats a persisted (DB) one; within one origin class the lexicographically
     * smallest source-qualified id wins.
     *
     * @param array<string, BindingSpecification> $specifications
     * @param array<string, int> $origins qualified id → ORIGIN_* rank
     *
     * @return array<string, BindingSpecification>
     */
    private function demoteExcessPromoted(array $specifications, array $origins): array
    {
        $promotedByType = [];
        foreach ($specifications as $qualifiedId => $specification) {
            if ($specification->isPromoted()) {
                $promotedByType[$specification->type()][] = $qualifiedId;
            }
        }

        foreach ($promotedByType as $type => $qualifiedIds) {
            if (\count($qualifiedIds) < 2) {
                continue;
            }

            $winner = array_reduce(
                $qualifiedIds,
                fn (?string $carry, string $candidate): string => $carry === null || $this->beats($candidate, $carry, $origins) ? $candidate : $carry,
                null,
            );

            if ($winner === null) {
                continue; // unreachable: the group carries at least two ids
            }

            foreach ($qualifiedIds as $qualifiedId) {
                if ($qualifiedId === $winner) {
                    continue;
                }

                $this->logger->warning(
                    \sprintf(
                        'Demoting binding specification "%s" for type "%s": at most one specification may be promoted per type, and "%s" wins.',
                        $qualifiedId,
                        $type,
                        $winner,
                    ),
                    ['qualifiedId' => $qualifiedId, 'type' => $type, 'winner' => $winner],
                );

                $specifications[$qualifiedId] = $specifications[$qualifiedId]->withoutPromotion();
            }
        }

        return $specifications;
    }

    /**
     * @param array<string, int> $origins qualified id → ORIGIN_* rank
     */
    private function beats(string $candidate, string $incumbent, array $origins): bool
    {
        // Tuple comparison ranks by origin first (authored < database), then by the lexicographically smaller id.
        return [$origins[$candidate], $candidate] < [$origins[$incumbent], $incumbent];
    }
}
