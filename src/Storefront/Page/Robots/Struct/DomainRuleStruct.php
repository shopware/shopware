<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;

#[Package('framework')]
class DomainRuleStruct extends Struct
{
    /**
     * @deprecated tag:v6.8.0 - Use getDirectives() instead
     *
     * @var array<array{type: string, path: string}>
     */
    private array $rules = [];

    /**
     * @var RobotsDirective[]
     */
    private array $directives = [];

    /**
     * @param ParsedRobots|string $rules The robots.txt rules as parsed object or deprecated string format
     */
    public function __construct(ParsedRobots|string $rules, private readonly string $basePath)
    {
        if ($rules instanceof ParsedRobots) {
            $this->initializeFromParsed($rules);
        } else {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Passing a string to DomainRuleStruct constructor is deprecated. Use RobotsDirectiveParser::parse() and pass the ParsedRobots object instead.'
            );
            $this->parseRulesFromString($rules);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Use getDirectives() instead
     *
     * @return array<array{type: string, path: string}>
     */
    public function getRules(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getDirectives')
        );

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
        // Collect orphaned path directives
        foreach ($parsed->orphanedPathDirectives as $directive) {
            $directiveWithPath = $directive->withBasePath($this->basePath);
            $this->directives[] = $directiveWithPath;
            $this->rules[] = ['type' => $directiveWithPath->type->value, 'path' => $directiveWithPath->value];
        }

        // Collect path directives from user-agent blocks
        foreach ($parsed->userAgentBlocks as $block) {
            foreach ($block->getPathDirectives() as $directive) {
                $directiveWithPath = $directive->withBasePath($this->basePath);
                $this->directives[] = $directiveWithPath;
                $this->rules[] = ['type' => $directiveWithPath->type->value, 'path' => $directiveWithPath->value];
            }
        }
    }

    private function parseRulesFromString(string $rules): void
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
            $this->directives[] = new RobotsDirective($directiveType, $normalizedPath);
        }
    }
}
