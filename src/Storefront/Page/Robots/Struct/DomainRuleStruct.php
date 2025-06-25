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
            if (!\in_array($ruleType, ['allow', 'disallow'], true)) {
                continue;
            }

            $path = $this->basePath . '/' . ltrim(trim($rule[1] ?? ''), '/');
            $this->rules[] = ['type' => ucfirst($ruleType), 'path' => '/' . ltrim($path, '/')];
        }
    }
}
