<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('framework')]
final class TestMappingCandidateRegistry extends AbstractContentSystemMappingCandidateRegistry
{
    /**
     * @param array<string, MappingCandidate>|\Closure(string): array<string, MappingCandidate> $candidates
     * @param MappingSourceResolution|\Closure(string, MappingSourceReference, MappingSourceResolutionContext): MappingSourceResolution $resolution
     * @param list<string> $cacheTags
     */
    public function __construct(
        private readonly array|\Closure $candidates = [],
        private readonly MappingSourceResolution|\Closure|null $resolution = null,
        private readonly array $cacheTags = [],
    ) {
    }

    public function getDecorated(): AbstractContentSystemMappingCandidateRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    public function reset(): void
    {
    }

    public function cacheTagsForMapping(string $rootSource, string $path): array
    {
        return $this->cacheTags;
    }

    public function forRootSource(string $rootSource): array
    {
        return $this->candidates instanceof \Closure ? ($this->candidates)($rootSource) : $this->candidates;
    }

    protected function resolveAdmittedSource(
        string $rootSource,
        MappingSourceReference $source,
        MappingSourceResolutionContext $context,
    ): MappingSourceResolution {
        if ($this->resolution instanceof \Closure) {
            return ($this->resolution)($rootSource, $source, $context);
        }

        return $this->resolution ?? MappingSourceResolution::noValue();
    }
}
