<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class DomainRuleStruct extends Struct
{
    /**
     * @var array<array{type: string, path: string}>
     */
    private array $rules = [];

    public function __construct(string $rules, private readonly string $basePath)
    {
        $this->parseRules($rules);
    }

    /**
     * @return array<array{type: string, path: string}>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    private function parseRules(string $rules): void
    {
        $rules = explode("\n", $rules);

        foreach ($rules as $rule) {
            $rule = explode(':', $rule, 2);

            $ruleType = mb_strtolower($rule[0] ?? '');

            // Skip empty or invalid rules
            if ($ruleType === '' || !isset($rule[1])) {
                continue;
            }

            $value = trim($rule[1]);

            // Handle path-based directives (Allow/Disallow) with base path
            if (\in_array($ruleType, ['allow', 'disallow'], true)) {
                $path = $this->basePath . '/' . ltrim($value, '/');
                $this->rules[] = ['type' => ucfirst($ruleType), 'path' => '/' . ltrim($path, '/')];
            } else {
                // Handle other directives (User-agent, Crawl-delay, etc.) without base path modification, use the value as it is
                $this->rules[] = ['type' => ucfirst($ruleType), 'path' => $value];
            }
        }
    }
}
