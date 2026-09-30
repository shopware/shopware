<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * This rule detects usage of the strip_tags() function, it silently drops everything after a stray "<",
 * so user input like "I <3 Kisses" is truncated to "I ".
 * Reference: https://www.php.net/manual/en/function.strip-tags.php
 *
 * @internal
 *
 * @implements Rule<FuncCall>
 */
#[Package('framework')]
class NoStripTagsRule implements Rule
{
    final public const FUNCTION_NAME = 'strip_tags';

    final public const RULE_IDENTIFIER = 'shopware.stripTags';

    final public const ERROR_MESSAGE = 'Usage of strip_tags() in class "%s" is disallowed, it drops everything after a stray "<". Use HtmlSanitizer::stripTags() for plain text.';

    /**
     * @var list<class-string>
     */
    private const ALLOWLIST = [
        'Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils',
    ];

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        if (!$node->name instanceof Name) {
            return [];
        }

        if (!$this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($node->name, $scope);

        if (!$function->isBuiltin() || $function->getName() !== self::FUNCTION_NAME) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName() ?? '';

        if (\in_array($className, self::ALLOWLIST, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $className))
                ->identifier(self::RULE_IDENTIFIER)
                ->build(),
        ];
    }
}
