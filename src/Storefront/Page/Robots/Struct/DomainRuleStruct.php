<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;

#[Package('framework')]
class DomainRuleStruct extends Struct
{
    /**
     * @var array<array{type: string, path: string}>
     */
    private array $rules = [];

    /**
     * @var RobotsDirective[]
     */
    private array $directives = [];

    /**
     * @param ParsedRobots|string $rules The robots.txt rules as string (legacy) or parsed object (new)
     *
     * @deprecated 6.7.4.0 - Passing a string as the first parameter is deprecated. Pass a ParsedRobots object instead.
     */
    public function __construct(ParsedRobots|string $rules, private readonly string $basePath)
    {
        if ($rules instanceof ParsedRobots) {
            $this->initializeFromParsed($rules);
        } else {
            // Legacy path for backward compatibility
            $this->parseRulesLegacy($rules);
        }
    }

    /**
     * @return array<array{type: string, path: string}>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return RobotsDirective[]
     */
    public function getDirectives(): array
    {
        return $this->directives;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    private function initializeFromParsed(ParsedRobots $parsed): void
    {
        // Collect orphaned path directives (for backward compatibility)
        foreach ($parsed->getOrphanedPathDirectives() as $directive) {
            $directiveWithPath = $directive->withBasePath($this->basePath);
            $this->directives[] = $directiveWithPath;
            $this->rules[] = ['type' => $directiveWithPath->type, 'path' => $directiveWithPath->value];
        }

        // Collect path directives from user-agent blocks
        foreach ($parsed->getUserAgentBlocks() as $block) {
            foreach ($block->getPathDirectives() as $directive) {
                $directiveWithPath = $directive->withBasePath($this->basePath);
                $this->directives[] = $directiveWithPath;
                $this->rules[] = ['type' => $directiveWithPath->type, 'path' => $directiveWithPath->value];
            }
        }
    }

    private function parseRulesLegacy(string $rules): void
    {
        $rules = explode("\n", $rules);

        foreach ($rules as $rule) {
            $rule = explode(':', $rule, 2);

            $ruleType = mb_strtolower($rule[0] ?? '');
            $directiveType = RobotsDirectiveType::tryFromInsensitive($ruleType);

            // Only allow path-based directives in legacy format
            if ($directiveType === null || !$directiveType->isPathBased()) {
                continue;
            }

            $path = $this->basePath . '/' . ltrim(trim($rule[1] ?? ''), '/');
            $normalizedPath = '/' . ltrim($path, '/');

            $this->rules[] = ['type' => $directiveType->value, 'path' => $normalizedPath];
            $this->directives[] = new RobotsDirective($directiveType->value, $normalizedPath);
        }
    }
}
