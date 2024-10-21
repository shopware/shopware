<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * This rule makes an attempt to validate if the ACL keys used in the in the method calls are valid.
 * The calls to `hasPermission` method are checked. This can lead to false positives in the future if classes
 * with hasPermission methods, that are not related to ACL, are added. In such case, the rule should
 * be updated.
 *
 * @internal
 *
 * @implements \PHPStan\Rules\Rule<Node>
 */
#[Package('core')]
class AclValidPermissionsInMethodRule implements Rule
{
    private const ERROR_MESSAGE = 'Permission "%s" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.';

    private AclValidPermissionsHelper $permissionsHelper;

    public function __construct(AclValidPermissionsHelper $permissionsHelper)
    {
        $this->permissionsHelper = $permissionsHelper;
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return array<array-key, RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        $methodName = $node->name;
        if ($methodName instanceof Identifier && $methodName->name === 'hasPermission') {
            $args = $node->args;
            if (isset($args[0]) && $args[0] instanceof Arg && $args[0]->value instanceof String_) {
                $permission = $args[0]->value->value;
                if (!$this->permissionsHelper->aclKeyValid($permission)) {
                    $errors[] = RuleErrorBuilder::message(\sprintf(self::ERROR_MESSAGE, $permission))
                        ->line($args[0]->getStartLine() ?: 0)
                        ->identifier('shopware.aclKey')
                        ->build();
                }
            }
        }

        return $errors;
    }
}
