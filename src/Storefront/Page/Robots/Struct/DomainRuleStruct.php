<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class DomainRuleStruct extends Struct
{
    /**
     * Rule types that require base path prefixing
     */
    private const PATH_BASED_RULES = ['allow', 'disallow'];

    /**
     * Rule types that use values as-is without path modification
     */
    private const VALUE_BASED_RULES = ['user-agent', 'crawl-delay', 'sitemap'];

    /**
     * @var array<array{type: string, path: string}>
     */
    private array $rules = [];

    /**
     * @var array<array{userAgent: string|null, rules: list<array{type: string, path: string}>}>
     */
    private array $userAgentBlocks = [];

    public function __construct(string $rules, private readonly string $basePath)
    {
        $this->parseRules($rules);
        $this->buildUserAgentBlocks();
    }

    /**
     * Get all rules (path-based and global)
     *
     * @return array<array{type: string, path: string}>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get user-agent blocks with their rules
     * Each block contains a user-agent and all its directives
     *
     * @return array<array{userAgent: string|null, rules: list<array{type: string, path: string}>}>
     */
    public function getUserAgentBlocks(): array
    {
        return $this->userAgentBlocks;
    }

    /**
     * Get only path-based rules (Allow/Disallow) for domain-specific output
     * Excludes empty path rules as those are global user-agent definitions
     *
     * Note: For proper robots.txt structure with user-agent blocks, use getUserAgentBlocks() instead
     *
     * @return array<array{type: string, path: string}>
     */
    public function getPathRules(): array
    {
        return array_filter($this->rules, function (array $rule): bool {
            $ruleType = mb_strtolower($rule['type']);

            // Only include path-based rules that have a non-empty path
            return \in_array($ruleType, self::PATH_BASED_RULES, true) && $rule['path'] !== '';
        });
    }

    /**
     * Get global rules: User-agent, Crawl-delay, Sitemap, and empty path rules
     * Empty path rules (e.g., "Disallow:") define user-agent behavior, not domain-specific paths
     *
     * Note: For proper robots.txt structure with user-agent blocks, use getUserAgentBlocks() instead
     *
     * @return array<array{type: string, path: string}>
     */
    public function getGlobalRules(): array
    {
        return array_filter($this->rules, function (array $rule): bool {
            $ruleType = mb_strtolower($rule['type']);

            // Include value-based rules (User-agent, Crawl-delay, Sitemap)
            if (\in_array($ruleType, self::VALUE_BASED_RULES, true)) {
                return true;
            }

            // Include path-based rules with empty paths (e.g., "Disallow:" or "Allow:")
            if (\in_array($ruleType, self::PATH_BASED_RULES, true) && $rule['path'] === '') {
                return true;
            }

            return false;
        });
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    private function parseRules(string $rules): void
    {
        $lines = explode("\n", $rules);

        foreach ($lines as $line) {
            $this->parseSingleRule($line);
        }
    }

    private function parseSingleRule(string $line): void
    {
        $parts = explode(':', $line, 2);

        if (!$this->isValidRuleLine($parts)) {
            return;
        }

        $ruleType = mb_strtolower(trim($parts[0]));
        $value = trim($parts[1]);

        if (!$this->isValidRuleType($ruleType)) {
            return;
        }

        // Value-based rules require a non-empty value
        if (!$this->isPathBasedRule($ruleType) && $value === '') {
            return;
        }

        $this->addRule($ruleType, $value);
    }

    /**
     * @param array<string> $parts
     */
    private function isValidRuleLine(array $parts): bool
    {
        return isset($parts[0], $parts[1])
            && $parts[0] !== '';
    }

    private function isValidRuleType(string $ruleType): bool
    {
        return \in_array($ruleType, self::PATH_BASED_RULES, true)
            || \in_array($ruleType, self::VALUE_BASED_RULES, true);
    }

    private function addRule(string $ruleType, string $value): void
    {
        if ($this->isPathBasedRule($ruleType)) {
            $this->addPathBasedRule($ruleType, $value);

            return;
        }

        $this->addValueBasedRule($ruleType, $value);
    }

    private function isPathBasedRule(string $ruleType): bool
    {
        return \in_array($ruleType, self::PATH_BASED_RULES, true);
    }

    private function addPathBasedRule(string $ruleType, string $value): void
    {
        // Empty path means allow/disallow nothing (wildcard), don't normalize
        if ($value === '') {
            $this->rules[] = [
                'type' => ucfirst($ruleType),
                'path' => '',
            ];

            return;
        }

        $normalizedPath = $this->normalizePath($value);

        $this->rules[] = [
            'type' => ucfirst($ruleType),
            'path' => $normalizedPath,
        ];
    }

    private function addValueBasedRule(string $ruleType, string $value): void
    {
        $this->rules[] = [
            'type' => ucfirst($ruleType),
            'path' => $value,
        ];
    }

    private function normalizePath(string $path): string
    {
        $prefixedPath = $this->basePath . '/' . ltrim($path, '/');

        return '/' . ltrim($prefixedPath, '/');
    }

    /**
     * Build user-agent blocks from parsed rules
     * Groups rules by user-agent directive for proper robots.txt structure
     */
    private function buildUserAgentBlocks(): void
    {
        $currentUserAgent = null;
        $currentBlock = [];

        foreach ($this->rules as $rule) {
            $ruleType = mb_strtolower($rule['type']);

            // User-agent starts a new block
            if ($ruleType === 'user-agent') {
                // Save previous block if it has rules
                if ($currentBlock !== []) {
                    $this->userAgentBlocks[] = [
                        'userAgent' => $currentUserAgent,
                        'rules' => $currentBlock,
                    ];
                }

                // Start new block
                $currentUserAgent = $rule['path'];
                $currentBlock = [];

                continue;
            }

            // Add rule to current block
            $currentBlock[] = $rule;
        }

        // Save last block
        if ($currentBlock !== [] || $currentUserAgent !== null) {
            $this->userAgentBlocks[] = [
                'userAgent' => $currentUserAgent,
                'rules' => $currentBlock,
            ];
        }
    }
}
