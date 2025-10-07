<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleCollection;

#[Package('framework')]
class RobotsPage extends Struct
{
    protected DomainRuleCollection $domainRules;

    /**
     * @var list<string>
     */
    protected array $sitemaps;

    public function getDomainRules(): DomainRuleCollection
    {
        return $this->domainRules;
    }

    public function setDomainRules(DomainRuleCollection $domainRules): void
    {
        $this->domainRules = $domainRules;
    }

    /**
     * @return list<string>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param list<string> $sitemaps
     */
    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }

    /**
     * Get merged user-agent blocks across all domains
     * Groups blocks by user-agent and merges their rules
     * Deduplicates non-path directives (Crawl-delay, empty Disallow, etc.)
     *
     * @return array<array{userAgent: string|null, rules: list<array{type: string, path: string}>}>
     */
    public function getMergedUserAgentBlocks(): array
    {
        $mergedBlocks = [];

        foreach ($this->domainRules as $domainRule) {
            foreach ($domainRule->getUserAgentBlocks() as $block) {
                $userAgent = $block['userAgent'];
                $key = $userAgent ?? 'default';

                if (!isset($mergedBlocks[$key])) {
                    $mergedBlocks[$key] = [
                        'userAgent' => $userAgent,
                        'rules' => [],
                        'seenNonPathRules' => [], // Track non-path rules to avoid duplicates
                    ];
                }

                foreach ($block['rules'] as $rule) {
                    $ruleType = mb_strtolower($rule['type']);
                    $isPathBased = \in_array($ruleType, ['allow', 'disallow'], true) && $rule['path'] !== '';

                    // For non-path rules, deduplicate by type+path
                    if (!$isPathBased) {
                        $ruleSignature = $rule['type'] . ':' . $rule['path'];
                        if (isset($mergedBlocks[$key]['seenNonPathRules'][$ruleSignature])) {
                            continue; // Skip duplicate
                        }
                        $mergedBlocks[$key]['seenNonPathRules'][$ruleSignature] = true;
                    }

                    $mergedBlocks[$key]['rules'][] = $rule;
                }
            }
        }

        return array_values(array_map(function (array $block): array {
            unset($block['seenNonPathRules']);

            return $block;
        }, $mergedBlocks));
    }
}
