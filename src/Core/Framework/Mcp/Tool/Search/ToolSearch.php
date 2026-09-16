<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 */
#[Package('framework')]
class ToolSearch
{
    private const DEFAULT_MAX_RESULTS = 3;
    private const MIN_SCORE = 1.0;

    /**
     * @param list<Tool> $tools
     *
     * @return list<ToolSearchResult>
     */
    public function search(array $tools, string $query, int $maxResults = self::DEFAULT_MAX_RESULTS): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $maxResults = max(1, $maxResults);
        $queryLower = mb_strtolower($query);
        $queryTokens = preg_split('/\s+/', $queryLower, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $normalizedQueryCompact = str_replace([' ', '_'], '', $queryLower);

        $results = [];
        foreach ($tools as $tool) {
            [$score, $matchedIn] = $this->scoreTool($tool, $queryLower, $queryTokens, $normalizedQueryCompact);
            if ($score <= self::MIN_SCORE) {
                continue;
            }

            $results[] = new ToolSearchResult($tool, $score, $matchedIn);
        }

        usort($results, static fn (ToolSearchResult $a, ToolSearchResult $b): int => $b->score <=> $a->score);

        return \array_slice($results, 0, $maxResults);
    }

    /**
     * @param list<string> $queryTokens
     *
     * @return array{float, list<string>}
     */
    private function scoreTool(Tool $tool, string $queryLower, array $queryTokens, string $normalizedQueryCompact): array
    {
        $nameLower = mb_strtolower($tool->name);
        $descriptionLower = mb_strtolower($tool->description ?? '');
        $normalizedNameCompact = str_replace('_', '', $nameLower);
        $nameTokens = $this->splitTokens($nameLower);
        $propertyNames = $this->lowerInputPropertyNames($tool->inputSchema);
        $matches = [];
        $score = 0.0;

        if (str_contains($nameLower, $queryLower)) {
            $score += 5.0;
            $matches[] = 'name:substring';
        }
        if (str_starts_with($nameLower, $queryLower)) {
            $score += 1.5;
            $matches[] = 'name:prefix';
        }
        if ($normalizedNameCompact === $normalizedQueryCompact && \count($queryTokens) > 1) {
            $score += 2.5;
            $matches[] = 'name:exact-tokens';
        }
        if ($descriptionLower !== '' && str_contains($descriptionLower, $queryLower)) {
            $score += 2.0;
            $matches[] = 'description';
        }

        foreach ($propertyNames as $propertyName) {
            if (str_contains($propertyName, $queryLower)) {
                $score += 1.0;
                $matches[] = 'parameter';
            }
        }

        $matchedTokens = [];
        foreach ($queryTokens as $token) {
            if (str_contains($nameLower, $token)) {
                $score += 1.0;
                $matchedTokens[$token] = true;
                $matches[] = 'name:token';
            } elseif (str_contains($descriptionLower, $token)) {
                $score += 0.6;
                $matchedTokens[$token] = true;
                $matches[] = 'description:token';
            }

            foreach ($propertyNames as $propertyName) {
                if (str_contains($propertyName, $token)) {
                    $score += 0.4;
                    $matchedTokens[$token] = true;
                    $matches[] = 'parameter:token';
                    break;
                }
            }
        }

        $score += \count($matchedTokens) * 0.8;
        if (\count($queryTokens) > 1 && \count($matchedTokens) === \count($queryTokens)) {
            $score += 2.0;
        }

        $nameTokenMatches = 0;
        foreach ($queryTokens as $queryToken) {
            foreach ($nameTokens as $nameToken) {
                if (str_contains($nameToken, $queryToken)) {
                    ++$nameTokenMatches;
                    break;
                }
            }
        }

        if ($nameTokenMatches === \count($queryTokens)) {
            $score += 4.0;
            if (\count($nameTokens) === \count($queryTokens)) {
                $score += 2.0;
            }
        }

        $extraTokens = \count($nameTokens) - $nameTokenMatches;
        if ($extraTokens > 0) {
            $score -= $extraTokens * 0.5;
        }

        $score += $this->normalizedSimilarity($nameLower, $queryLower) * 2.0;
        $score += $this->normalizedSimilarity($descriptionLower, $queryLower) * 0.8;
        $score += $this->bestPropertySimilarity($propertyNames, $queryLower) * 0.6;
        $score += $this->normalizedSimilarity(trim($nameLower . ' ' . $descriptionLower . ' ' . implode(' ', $propertyNames)), $queryLower) * 0.5;

        return [$score, array_values(array_unique($matches))];
    }

    /**
     * @param array<string, mixed> $inputSchema
     *
     * @return list<string>
     */
    private function lowerInputPropertyNames(array $inputSchema): array
    {
        if (!\is_array($inputSchema['properties'] ?? null)) {
            return [];
        }

        return array_map(static fn (string|int $propertyName): string => mb_strtolower((string) $propertyName), array_keys($inputSchema['properties']));
    }

    /**
     * @return list<string>
     */
    private function splitTokens(string $value): array
    {
        return preg_split('/[_\-\s]+/', $value, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param list<string> $propertyNames
     */
    private function bestPropertySimilarity(array $propertyNames, string $queryLower): float
    {
        $best = 0.0;
        foreach ($propertyNames as $propertyName) {
            $best = max($best, $this->normalizedSimilarity($propertyName, $queryLower));
        }

        return $best;
    }

    private function normalizedSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        $distance = levenshtein($a, $b);
        $maxLength = max(\strlen($a), \strlen($b));

        return max(0.0, 1 - ($distance / $maxLength));
    }
}
