<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\SharedTwig;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\VarLikeIdentifier;
use PHPStan\Analyser\Scope;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\TemplateWrapper;

/**
 * Traces where a Twig environment comes from: the shared one of the service container, one the test
 * builds itself, or a reference (variable or property) whose assignments are collected separately.
 *
 * @internal
 */
#[Package('framework')]
final class SharedTwigOrigin
{
    public const SHARED = 'shared';

    public const OWN = 'own';

    public const OTHER = 'other';

    /**
     * Prefix of an origin that is another reference (`$this->twig = $twig;`), resolved by the rule.
     */
    public const ALIAS_PREFIX = 'alias:';

    private const SHARED_TWIG_SERVICE_IDS = ['twig', Environment::class, TwigEnvironment::class];

    private const TEMPLATE_FACTORY_METHODS = ['load', 'createtemplate', 'resolvetemplate'];

    /**
     * A stable key for the reference an environment is stored in, or null when it is not a plain
     * variable, `$this->property` or `self::$property`/`static::$property`. Variables are keyed per
     * function, properties per name (resolved over the class hierarchy by the rule).
     */
    public static function referenceKey(Expr $expr, Scope $scope): ?string
    {
        if ($expr instanceof Variable && \is_string($expr->name)) {
            return 'var:' . ($scope->getFunctionName() ?? '') . ':' . $expr->name;
        }

        if ($expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return 'prop:' . $expr->name->name;
        }

        if ($expr instanceof StaticPropertyFetch
            && $expr->class instanceof Name
            && \in_array($expr->class->toLowerString(), ['self', 'static'], true)
            && $expr->name instanceof VarLikeIdentifier
        ) {
            return 'prop:' . $expr->name->name;
        }

        return null;
    }

    /**
     * `...->get('twig')` (or its class aliases) on a service container: the one Twig environment the
     * Storefront renders with, carrying `TemplateDataExtension`. Other environments of the container,
     * like the SEO URL one, have their own instance and are not affected.
     */
    public static function isSharedTwigLookup(Expr $expr, Scope $scope): bool
    {
        if (!self::isContainerGet($expr, $scope)) {
            return false;
        }

        \assert($expr instanceof MethodCall);
        $serviceId = $expr->getArgs()[0] ?? null;
        if ($serviceId === null) {
            return false;
        }

        foreach ($scope->getType($serviceId->value)->getConstantStrings() as $id) {
            if (\in_array($id->getValue(), self::SHARED_TWIG_SERVICE_IDS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `...->get(...)` on a service container, whatever service it asks for.
     */
    public static function isContainerGet(Expr $expr, Scope $scope): bool
    {
        if (!$expr instanceof MethodCall || !$expr->name instanceof Identifier || $expr->name->toLowerString() !== 'get') {
            return false;
        }

        return (new ObjectType(ContainerInterface::class))->isSuperTypeOf($scope->getType($expr->var))->yes();
    }

    public static function isNewEnvironment(Expr $expr, Scope $scope): bool
    {
        if (!$expr instanceof New_ || !$expr->class instanceof Name) {
            return false;
        }

        return (new ObjectType(Environment::class))->isSuperTypeOf(new ObjectType($scope->resolveName($expr->class)))->yes();
    }

    /**
     * The environment a `load()`/`createTemplate()`/`resolveTemplate()` call hands out a template from. Those
     * calls only compile and wrap the template; Twig resolves its globals when the template is rendered.
     */
    public static function templateSource(Expr $expr, Scope $scope): ?Expr
    {
        if (!$expr instanceof MethodCall
            || !$expr->name instanceof Identifier
            || !\in_array($expr->name->toLowerString(), self::TEMPLATE_FACTORY_METHODS, true)
            || !self::isEnvironment($expr->var, $scope)
        ) {
            return null;
        }

        return $expr->var;
    }

    public static function isTemplateWrapper(Expr $expr, Scope $scope): bool
    {
        $type = $scope->getType($expr);
        if ($type instanceof NeverType || $type->isObject()->no()) {
            return false;
        }

        return (new ObjectType(TemplateWrapper::class))->isSuperTypeOf($type)->yes();
    }

    public static function isEnvironment(Expr $expr, Scope $scope): bool
    {
        $type = $scope->getType($expr);
        // `never` is a subtype of everything, an unreachable receiver must not count as an environment
        if ($type instanceof NeverType || $type->isObject()->no()) {
            return false;
        }

        return (new ObjectType(Environment::class))->isSuperTypeOf($type)->yes();
    }
}
