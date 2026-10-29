<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * A script service that keeps one `Context` reuses it for every call, so a script that changes a returned context changes all later calls.
 *
 * @internal
 *
 * @implements Rule<InClassNode>
 */
#[Package('framework')]
class ScriptServiceContextPropertyRule implements Rule
{
    public const ERROR_MESSAGE = 'Script service %s must not keep a Context in property $%s, every call needs its own context.';

    public const ERROR_TIP = 'Inject Shopware\Core\Framework\Script\AppContextFactory and call create() in every method.';

    /**
     * @var list<class-string>
     */
    private const ALLOWED_CLASSES = [];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classNode = $node->getOriginalNode();
        $class = $node->getClassReflection();

        if (!$this->isScriptService($classNode) || \in_array($class->getName(), self::ALLOWED_CLASSES, true)) {
            return [];
        }

        $errors = [];
        foreach ($this->collectProperties($classNode) as $propertyName => $line) {
            if ($this->holdsContext($class, $propertyName)) {
                $errors[] = $this->buildError($class->getName(), $propertyName, $line);
            }
        }

        return $errors;
    }

    private function isScriptService(ClassLike $classNode): bool
    {
        return str_contains($classNode->getDocComment()?->getText() ?? '', '@script-service');
    }

    /**
     * @return array<string, int> property name => line
     */
    private function collectProperties(ClassLike $classNode): array
    {
        $properties = [];

        foreach ($classNode->getProperties() as $property) {
            foreach ($property->props as $prop) {
                $properties[$prop->name->toString()] = $property->getStartLine();
            }
        }

        foreach ($classNode->getMethod('__construct')->params ?? [] as $param) {
            if ($param->flags !== 0 && $param->var instanceof Node\Expr\Variable && \is_string($param->var->name)) {
                $properties[$param->var->name] = $param->getStartLine();
            }
        }

        return $properties;
    }

    private function holdsContext(ClassReflection $class, string $propertyName): bool
    {
        if (!$class->hasNativeProperty($propertyName)) {
            return false;
        }

        $type = TypeCombinator::removeNull($class->getNativeProperty($propertyName)->getNativeType());

        return (new ObjectType(Context::class))->isSuperTypeOf($type)->yes();
    }

    private function buildError(string $className, string $propertyName, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $className, $propertyName))
            ->identifier('shopware.scriptServiceContextProperty')
            ->tip(self::ERROR_TIP)
            ->line($line)
            ->build();
    }
}
