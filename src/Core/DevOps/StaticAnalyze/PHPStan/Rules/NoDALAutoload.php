<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<New_>
 *
 * @internal
 */
#[Package('core')]
class NoDALAutoload implements Rule
{
    use InTestClassTrait;

    private const ASSOCIATIONS_WITH_AUTOLOAD = [
        OneToOneAssociationField::class,
        ManyToOneAssociationField::class,
    ];

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @param New_ $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->isInClass()) {
            return [];
        }

        if ($this->isInTestClass($scope)) {
            //if in a test namespace, don't care
            return [];
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        if (!\in_array($node->class->toString(), self::ASSOCIATIONS_WITH_AUTOLOAD, true)) {
            return [];
        }

        $classReflection = $this->reflectionProvider
            ->getClass($node->class->toString())
            ->getNativeReflection();

        $construct = $classReflection->getMethod('__construct');

        $autoloadParamPosition = null;
        $propertyNameParamPosition = null;
        foreach ($construct->getParameters() as $param) {
            if ($param->name === 'autoload') {
                $autoloadParamPosition = $param->getPosition();
            }

            if ($param->name === 'propertyName') {
                $propertyNameParamPosition = $param->getPosition();
            }
        }

        if ($autoloadParamPosition === null || $propertyNameParamPosition === null) {
            //cannot find autoload or propertyName parameter
            return [];
        }

        if (!isset($node->getArgs()[$autoloadParamPosition])) {
            //autoload parameter not passed
            return [];
        }

        $autoloadValueExpr = $node->getArgs()[$autoloadParamPosition]->value;
        $propertyNameValueExpr = $node->getArgs()[$propertyNameParamPosition]->value;

        if ($scope->getType($autoloadValueExpr)->isTrue()->yes()) {
            $definitionClassReflection = $scope->getClassReflection()->getNativeReflection();

            $constant = $definitionClassReflection->getReflectionConstant('ENTITY_NAME');

            if ($constant === false) {
                return [];
            }

            $constantValue = $constant->getValueExpression();
            if (!$constantValue instanceof String_) {
                return [];
            }

            $propType = $scope->getType($propertyNameValueExpr);
            if ($propType->getConstantStrings() === []) {
                return [];
            }

            return [
                RuleErrorBuilder::message(sprintf(
                    '%s.%s association has a configured autoload===true, this is forbidden for platform integrations',
                    $constantValue->value,
                    $propType->getConstantStrings()[0]->getValue()
                ))->build(),
            ];
        }

        return [];
    }
}
