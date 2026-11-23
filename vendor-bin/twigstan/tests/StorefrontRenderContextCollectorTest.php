<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Twig\ErrorTemplateStruct;
use Shopware\Storefront\Framework\Twig\NavigationInfo;

/**
 * @internal
 */
#[Package('framework')]
class StorefrontRenderContextCollectorTest extends PHPStanTestCase
{
    #[DataProvider('renderMethods')]
    public function testCollectsNamedRenderArgumentsAndTheirContext(string $methodName, bool $storefront): void
    {
        $receiver = new Node\Expr\Variable('this');
        $template = new Node\Scalar\String_('@Storefront/example.html.twig');
        $parameters = new Node\Expr\Variable('parameters');
        $node = new Node\Expr\MethodCall($receiver, $methodName, [
            new Node\Arg($parameters, name: new Node\Identifier('parameters')),
            new Node\Arg($template, name: new Node\Identifier('view')),
        ], ['startLine' => 42]);

        $context = ConstantArrayTypeBuilder::createEmpty();
        $context->setOffsetValueType(new ConstantStringType('page'), new ConstantStringType('example'));
        $override = ConstantArrayTypeBuilder::createEmpty();
        $override->setOffsetValueType(new ConstantStringType('context'), new ConstantStringType('override'));
        $optionalOverride = ConstantArrayTypeBuilder::createEmpty();
        $optionalOverride->setOffsetValueType(new ConstantStringType('context'), new ConstantStringType('override'), true);

        $scope = static::createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $scope->method('getType')->willReturnCallback(static fn (Node\Expr $expr) => match (true) {
            $expr instanceof Node\Expr\Variable && $expr->name === 'this' => new ObjectType(StorefrontController::class),
            $expr instanceof Node\Expr\Variable && $expr->name === 'errorTemplate' => new ObjectType(ErrorTemplateStruct::class),
            $expr instanceof Node\Expr\MethodCall => new StringType(),
            $expr instanceof Node\Scalar\String_ => new ConstantStringType($expr->value),
            $expr instanceof Node\Expr\Variable && $expr->name === 'parameters' => $context->getArray(),
            $expr instanceof Node\Expr\Variable && $expr->name === 'override' => $override->getArray(),
            $expr instanceof Node\Expr\Variable && $expr->name === 'optionalOverride' => $optionalOverride->getArray(),
            default => new MixedType(),
        });
        $scope->method('getFile')->willReturn('/controller.php');

        $method = static::createStub(ExtendedMethodReflection::class);
        $variant = new FunctionVariant(TemplateTypeMap::createEmpty(), null, [
            // @phpstan-ignore phpstanApi.constructor
            new NativeParameterReflection('view', false, new MixedType(), PassedByReference::createNo(), false, null),
            // @phpstan-ignore phpstanApi.constructor
            new NativeParameterReflection('parameters', true, new MixedType(), PassedByReference::createNo(), false, null),
        ], false, new MixedType());
        $method->method('getVariants')->willReturn([$variant]);
        $method->method('getNamedArgumentsVariants')->willReturn(null);
        $scope->method('getMethodReflection')->willReturn($method);

        $result = (new StorefrontRenderContextCollector())->processNode($node, $scope);

        static::assertNotNull($result);
        static::assertSame('@Storefront/example.html.twig', $result[0]['template']);
        static::assertStringContainsString('page: \'example\'', $result[0]['context']);
        static::assertStringContainsString(($storefront ? 'context: \\' : 'context?: \\') . SalesChannelContext::class, $result[0]['context']);
        static::assertStringContainsString('navigation: \\' . NavigationInfo::class, $result[0]['context']);

        static::assertStringContainsString(($storefront ? 'shopware: ' : 'shopware?: ') . 'array{', $result[0]['context']);
        static::assertSame('/controller.php', $result[0]['sourceLocation']->fileName);
        static::assertSame(42, $result[0]['sourceLocation']->lineNumber);

        $withOverride = new Node\Expr\MethodCall($receiver, $methodName, [new Node\Arg($template), new Node\Arg(new Node\Expr\Variable('override'))]);
        $result = (new StorefrontRenderContextCollector())->processNode($withOverride, $scope);
        static::assertNotNull($result);
        static::assertStringContainsString('context: \'override\'', $result[0]['context']);

        $withOptionalOverride = new Node\Expr\MethodCall($receiver, $methodName, [new Node\Arg($template), new Node\Arg(new Node\Expr\Variable('optionalOverride'))]);
        $result = (new StorefrontRenderContextCollector())->processNode($withOptionalOverride, $scope);
        static::assertNotNull($result);
        static::assertStringContainsString('\'override\'', $result[0]['context']);
        static::assertStringContainsString(SalesChannelContext::class, $result[0]['context']);
        static::assertStringContainsString($storefront ? 'context: ' : 'context?: ', $result[0]['context']);

        $withoutParameters = new Node\Expr\MethodCall($receiver, $methodName, [new Node\Arg($template)]);
        $result = (new StorefrontRenderContextCollector())->processNode($withoutParameters, $scope);
        static::assertNotNull($result);
        static::assertStringContainsString('navigation: \\' . NavigationInfo::class, $result[0]['context']);

        $dynamicTemplate = new Node\Expr\MethodCall($receiver, $methodName, [new Node\Arg(new Node\Expr\Variable('view'))]);
        static::assertNull((new StorefrontRenderContextCollector())->processNode($dynamicTemplate, $scope));

        $errorTemplate = new Node\Expr\MethodCall(new Node\Expr\Variable('errorTemplate'), 'getTemplateName');
        $errorRender = new Node\Expr\MethodCall($receiver, $methodName, [new Node\Arg($errorTemplate), new Node\Arg($parameters)]);
        $result = (new StorefrontRenderContextCollector())->processNode($errorRender, $scope);
        static::assertNotNull($result);
        static::assertSame([
            '@Storefront/storefront/page/error/error-404.html.twig',
            '@Storefront/storefront/page/error/error-ajax-404.html.twig',
            '@Storefront/storefront/page/error/error-ajax-std.html.twig',
            '@Storefront/storefront/page/error/error-std.html.twig',
        ], array_column($result, 'template'));
        foreach ($result as $renderPoint) {
            static::assertStringContainsString('page: \'example\'', $renderPoint['context']);
        }
    }

    public function testIgnoresUnrelatedMethodCalls(): void
    {
        $node = new Node\Expr\MethodCall(new Node\Expr\Variable('this'), 'redirect');

        $scope = static::createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);

        static::assertNull((new StorefrontRenderContextCollector())->processNode($node, $scope));

        $scope->method('getType')->willReturn(new ObjectType(\stdClass::class));
        $node = new Node\Expr\MethodCall(new Node\Expr\Variable('this'), 'renderView');
        static::assertNull((new StorefrontRenderContextCollector())->processNode($node, $scope));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function renderMethods(): iterable
    {
        yield 'storefront rendering' => ['renderStorefront', true];
        yield 'direct rendering' => ['render', false];
        yield 'view rendering' => ['renderView', false];
    }
}
