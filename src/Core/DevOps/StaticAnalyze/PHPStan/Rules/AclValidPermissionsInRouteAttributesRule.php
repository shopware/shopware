<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * This rule makes an attempt to validate if the ACL keys used in the controllers and controllers methods route attributes
 * are valid. The rule does not validate attributes itself, so it return empty errors list if it can't parse acl names.
 *
 * @internal
 *
 * @implements \PHPStan\Rules\Rule<InClassNode>
 */
#[Package('core')]
class AclValidPermissionsInRouteAttributesRule implements Rule
{
    private const ERROR_MESSAGE = 'Permission "%s" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.';

    private AclValidPermissionsHelper $permissionsHelper;

    public function __construct(AclValidPermissionsHelper $permissionsHelper)
    {
        $this->permissionsHelper = $permissionsHelper;
    }

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
        if (!$node->getClassReflection()->is(AbstractController::class)) {
            return [];
        }

        $errors = [];
        $controllerReflection = $node->getClassReflection()->getNativeReflection();
        \assert($controllerReflection instanceof \ReflectionClass);
        $classRouteAttr = $this->getRouteAnnotation($controllerReflection->getAttributes());
        if ($classRouteAttr !== null) {
            $errors = array_merge($errors, $this->validateAttribute($classRouteAttr));
        }

        foreach ($controllerReflection->getMethods() as $method) {
            // do not check inherited methods
            if ($method->getDeclaringClass()->name !== $controllerReflection->getName()) {
                continue;
            }

            $methodRouteAttr = $this->getRouteAnnotation($method->getAttributes());

            // skip methods without route annotation
            if ($methodRouteAttr === null) {
                continue;
            }
            $errors = array_merge($errors, $this->validateAttribute($methodRouteAttr));
        }

        return $errors;
    }

    /**
     * @param list<ReflectionAttribute|FakeReflectionAttribute> $attributes
     */
    private function getRouteAnnotation(array $attributes): ?ReflectionAttribute
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === Route::class) {
                \assert($attribute instanceof ReflectionAttribute);

                return $attribute;
            }
        }

        return null;
    }

    /**
     * @return RuleError[]
     */
    private function validateAttribute(ReflectionAttribute $attribute): array
    {
        $errors = [];
        $defaults = $attribute->getArgumentsExpressions()['defaults'] ?? null;

        if (!$defaults instanceof Array_) {
            return $errors;
        }

        foreach ($defaults->items as $item) {
            if ($item instanceof ArrayItem) {
                $key = $item->key;
                if ($key instanceof String_ && $key->value === PlatformRequest::ATTRIBUTE_ACL) {
                    if (!$item->value instanceof Array_) {
                        return $errors;
                    }
                    $acls = $item->value->items;
                    foreach ($acls as $acl) {
                        if (!$acl instanceof ArrayItem) {
                            continue;
                        }
                        $permissionNode = $acl->value;
                        if (!$permissionNode instanceof String_) {
                            return $errors;
                        }
                        $permission = $permissionNode->value;

                        if (!$this->permissionsHelper->aclKeyValid($permission)) {
                            $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $permission))
                                ->line($permissionNode->getStartLine() ?: 0)
                                ->identifier('shopware.aclKey')
                                ->build();
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
