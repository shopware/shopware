<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Robots\Parser\ParsedRobots;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;

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
     * @var list<RobotsDirective>
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
     * @return list<RobotsDirective>
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
        $allDirectives = array_merge(
            $parsed->orphanedPathDirectives,
            ...array_map(fn (RobotsUserAgentBlock $block) => $block->getPathDirectives(), $parsed->userAgentBlocks)
        );

        foreach ($allDirectives as $directive) {
            $directiveWithPath = $directive->withBasePath($this->basePath);
            $this->directives[] = $directiveWithPath;

            if (!Feature::isActive('v6.8.0.0')) {
                $this->rules[] = ['type' => $directiveWithPath->type->value, 'path' => $directiveWithPath->value];
            }
        }
    }

    private function parseRulesFromString(string $rules): void
    {
        $lines = explode("\n", $rules);

        foreach ($lines as $line) {
            $directive = RobotsDirectiveParser::parseDirectiveFromString($line);

            if ($directive === null) {
                continue;
            }

            $directiveWithPath = $directive->withBasePath($this->basePath);
            $this->directives[] = $directiveWithPath;

            if (!Feature::isActive('v6.8.0.0')) {
                $this->rules[] = ['type' => $directiveWithPath->type->value, 'path' => $directiveWithPath->value];
            }
        }
    }
}
