<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlParser;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * `XmlUtils::parse()` and `XmlUtils::loadFile()` resolve a document's DTD before they reject it,
 * which allows XML external entity injection through extension XML files. Use `XmlParser` instead.
 *
 * @internal
 *
 * @implements Rule<StaticCall>
 */
#[Package('framework')]
class NoXmlUtilsParsingRule implements Rule
{
    final public const RULE_IDENTIFIER = 'shopware.xmlUtilsParsing';

    final public const ERROR_MESSAGE = 'Do not call XmlUtils::%s(), it resolves the DTD of the parsed document. Use \Shopware\Core\Framework\Util\XmlParser::%s() instead.';

    private const DISALLOWED_METHODS = ['parse', 'loadFile'];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || !$node->class instanceof Name) {
            return [];
        }

        $method = $node->name->toString();

        if (!\in_array($method, self::DISALLOWED_METHODS, true)) {
            return [];
        }

        if ($scope->resolveName($node->class) !== XmlUtils::class) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection?->getName() === XmlParser::class) {
            return [];
        }

        // Tests parse their own committed fixtures, which are no attack surface.
        if ($classReflection !== null && TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $method, $method))
                ->identifier(self::RULE_IDENTIFIER)
                ->build(),
        ];
    }
}
