<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @implements Rule<Expression>
 */
#[Package('framework')]
class NoUselessVarAnnotationRule implements Rule
{
    public function __construct(
        private readonly PhpDocParser $phpDocParser,
        private readonly Lexer $lexer,
        private readonly TypeNodeResolver $typeNodeResolver,
    ) {
    }

    public function getNodeType(): string
    {
        return Expression::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Expression || !$node->expr instanceof Assign) {
            return [];
        }

        $assign = $node->expr;
        if (!$assign->var instanceof Variable || !\is_string($assign->var->name)) {
            return [];
        }

        $doc = $node->getDocComment();
        if ($doc === null) {
            return [];
        }

        $docText = $doc->getText();
        if (!str_contains($docText, '@var')) {
            return [];
        }

        static $phpDocCache = [];
        if (!isset($phpDocCache[$docText])) {
            $tokens = new TokenIterator($this->lexer->tokenize($docText));
            $phpDocCache[$docText] = $this->phpDocParser->parse($tokens);
        }
        $phpDocNode = $phpDocCache[$docText];
        if (!$phpDocNode instanceof PhpDocNode) {
            return [];
        }

        $varTags = $phpDocNode->getVarTagValues();
        if ($varTags === []) {
            return [];
        }

        $varName = $assign->var->name;
        $annotatedType = null;

        foreach ($varTags as $tag) {
            try {
                if ($tag->variableName !== null && $tag->variableName !== '') {
                    if ($tag->variableName === '$' . $varName) {
                        $nameScope = new NameScope($scope->getNamespace(), []);
                        $annotatedType = $this->typeNodeResolver->resolve($tag->type, $nameScope);
                        break;
                    }
                    continue;
                }

                if ($annotatedType === null) {
                    $nameScope = new NameScope($scope->getNamespace(), []);
                    $annotatedType = $this->typeNodeResolver->resolve($tag->type, $nameScope);
                }
            } catch (\Throwable) {
                // Ignore unparseable/unsupported phpdoc types to avoid crashing analysis
                continue;
            }
        }

        if ($annotatedType === null) {
            return [];
        }

        $rhsType = $scope->getType($assign->expr);

        if ($this->isRedundant($annotatedType, $rhsType)) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Useless @var annotation for $%s: annotated as %s but expression is already %s',
                    $varName,
                    $annotatedType->describe(VerbosityLevel::typeOnly()),
                    $rhsType->describe(VerbosityLevel::typeOnly()),
                ))
                    ->identifier('shopware.uselessVarAnnotation')
                    ->build(),
            ];
        }

        return [];
    }

    private function isRedundant(Type $annotatedType, Type $rhsType): bool
    {
        if ($annotatedType->equals($rhsType)) {
            return true;
        }

        return false;
    }
}
