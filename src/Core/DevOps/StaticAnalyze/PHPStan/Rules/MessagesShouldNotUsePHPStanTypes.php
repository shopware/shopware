<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface;

/**
 * @internal
 *
 * @implements Rule<InClassNode>
 *
 * Messages making use of the @phpstan-type or @phpstan-import-type annotations might lead to exceptions during message
 * handling. The Symfony Serializer, depending on the installed packages, misinterprets PHPStan types for classes and
 * serialization fails.
 *
 * @see https://github.com/symfony/symfony/pull/44451
 */
#[Package('core')]
class MessagesShouldNotUsePHPStanTypes implements Rule
{
    use InTestClassTrait;

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $ref = $scope->getClassReflection();

        if ($ref === null) {
            return [];
        }

        if (!$ref->implementsInterface(AsyncMessageInterface::class) && !$ref->implementsInterface(LowPriorityMessageInterface::class)) {
            return [];
        }

        $classDocComment = $node->getDocComment();

        if ($classDocComment === null) {
            return [];
        }

        $phpDocNode = $this->parsePhpDoc($classDocComment->getText());

        $errors = [];
        foreach ($phpDocNode->getTags() as $line => $tag) {
            if (!$tag instanceof PhpDocTagNode) {
                continue;
            }

            if ($tag->name === '@phpstan-type') {
                $errors[] = RuleErrorBuilder::message('Messages should not use @phpstan-type annotations')->line($classDocComment->getStartLine() + $line + 1)->build();
            }

            if ($tag->name === '@phpstan-import-type') {
                $errors[] = RuleErrorBuilder::message('Messages should not use @phpstan-import-type annotations')->line($classDocComment->getStartLine() + $line + 1)->build();
            }
        }

        return $errors;
    }

    private function parsePhpDoc(string $tokens): PhpDocNode
    {
        $lexer = new Lexer();
        $constExprParser = new ConstExprParser();
        $typeParser = new TypeParser($constExprParser);
        $phpDocParser = new PhpDocParser($typeParser, $constExprParser);

        $tokens = new TokenIterator($lexer->tokenize($tokens));

        return $phpDocParser->parse($tokens);
    }
}
