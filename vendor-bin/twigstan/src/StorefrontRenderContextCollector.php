<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Twig\ErrorTemplateStruct;
use Shopware\Storefront\Framework\Twig\NavigationInfo;
use Symfony\Component\Finder\Finder;
use TwigStan\PHPStan\Collector\TemplateContextCollector;
use TwigStan\Twig\SourceLocation;

/**
 * @implements TemplateContextCollector<Node\Expr\MethodCall>
 *
 * @internal
 */
#[Package('framework')]
final class StorefrontRenderContextCollector implements TemplateContextCollector
{
    public function __construct(
        private readonly string $viewsDirectory = __DIR__ . '/../../../src/Storefront/Resources/views',
        private readonly ?RenderEventData $renderEventData = null,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    /**
     * @param Scope&DependencyTracker $scope
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node->var instanceof Node\Expr\Variable
            || $node->var->name !== 'this'
            || !$node->name instanceof Node\Identifier
            || !\in_array($node->name->name, ['renderStorefront', 'render', 'renderView'], true)
        ) {
            return null;
        }

        $controllerType = $scope->getType($node->var);
        if (!(new ObjectType(StorefrontController::class))->isSuperTypeOf($controllerType)->yes()) {
            return null;
        }

        $method = $scope->getMethodReflection($controllerType, $node->name->name);
        if ($method === null) {
            return null;
        }

        $call = ArgumentsNormalizer::reorderMethodArguments(
            ParametersAcceptorSelector::selectFromArgs($scope, $node->getArgs(), $method->getVariants(), $method->getNamedArgumentsVariants()),
            $node,
        );
        if ($call === null || !isset($call->getArgs()[0])) {
            return null;
        }

        $args = $call->getArgs();
        $templates = $this->getTemplates($args[0]->value, $scope);
        $context = isset($args[1]) ? $scope->getType($args[1]->value) : new ConstantArrayType([], []);
        $context = $this->withDefaults($context, $node->name->name === 'renderStorefront');

        $result = [];
        foreach ($templates as $template) {
            $renderContext = $node->name->name === 'renderStorefront' && $this->renderEventData !== null
                ? $this->renderEventData->applyToRender($context, $template->getValue(), $scope)
                : $context;
            $result[] = [
                'sourceLocation' => new SourceLocation($scope->getFile(), $node->getStartLine()),
                'template' => $template->getValue(),
                'context' => (new Printer())->print($renderContext->toPhpDocNode()),
            ];
        }

        return $result !== [] ? $result : null;
    }

    /**
     * @return list<ConstantStringType>
     */
    private function getTemplates(Node\Expr $expression, Scope $scope): array
    {
        $templates = $scope->getType($expression)->getConstantStrings();
        if ($templates !== []) {
            return $templates;
        }

        if (!$expression instanceof Node\Expr\MethodCall
            || !$expression->name instanceof Node\Identifier
            || $expression->name->name !== 'getTemplateName'
            || !(new ObjectType(ErrorTemplateStruct::class))->isSuperTypeOf($scope->getType($expression->var))->yes()
        ) {
            return [];
        }

        // ErrorTemplateResolver selects a status-specific template or the standard fallback.
        foreach ((new Finder())->files()->in($this->viewsDirectory . '/storefront/page/error')->depth('== 0')->name('/^error(?:-ajax)?-(?:[0-9]+|std)\.html\.twig$/')->sortByName() as $file) {
            $templates[] = new ConstantStringType('@Storefront/storefront/page/error/' . $file->getFilename());
        }

        return $templates;
    }

    private function withDefaults(Type $context, bool $storefront): Type
    {
        if (!$context->isConstantArray()->yes()) {
            return $context;
        }

        $shopware = ConstantArrayTypeBuilder::createEmpty();
        $shopware->setOffsetValueType(new ConstantStringType('dateFormat'), new ConstantStringType(\DATE_ATOM));
        $shopware->setOffsetValueType(new ConstantStringType('navigation'), new ObjectType(NavigationInfo::class));
        $shopware->setOffsetValueType(new ConstantStringType('minSearchLength'), new IntegerType());
        $shopware->setOffsetValueType(new ConstantStringType('showStagingBanner'), new BooleanType());

        // Request attributes have no enforced value types.
        $defaults = [
            'shopware' => $shopware->getArray(),
            'themeId' => new MixedType(),
            'context' => new ObjectType(SalesChannelContext::class),
            'activeRoute' => new MixedType(),
            'formViolations' => new MixedType(),
            'jsonLdFlags' => new ConstantIntegerType(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP),
            'controllerName' => new StringType(),
            'controllerAction' => new StringType(),
        ];
        if ($storefront) {
            $defaults['headerParameters'] = new ArrayType(new MixedType(), new MixedType());
            $defaults['footerParameters'] = new ArrayType(new MixedType(), new MixedType());
        }

        $contexts = [];
        foreach ($context->getConstantArrays() as $array) {
            $builder = ConstantArrayTypeBuilder::createFromConstantArray($array);
            foreach ($defaults as $name => $type) {
                $key = new ConstantStringType($name);
                $hasKey = $array->hasOffsetValueType($key);
                if ($hasKey->yes()) {
                    continue;
                }

                if ($hasKey->maybe()) {
                    $type = TypeCombinator::union($type, $array->getOffsetValueType($key));
                }

                $optional = !$storefront || \in_array($name, ['controllerName', 'controllerAction'], true);
                $builder->setOffsetValueType($key, $type, $optional);
            }
            $contexts[] = $builder->getArray();
        }

        return TypeCombinator::union(...$contexts);
    }
}
