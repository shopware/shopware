<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @package core
 * @implements Rule<InClassNode>
 *
 * @internal
 */
class FinalClassRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->getClassReflection()->isFinal()) {
            return [];
        }

        if ($this->isMessageHandler($node)) {
            $classDeprecation = $node->getClassReflection()->getDeprecatedDescription() ?? '';
            /**
             * @deprecated tag:v6.5.0 - remove deprecation check, as all message handlers become final in v6.5.0
             */
            if (\str_contains($classDeprecation, 'tag:v6.5.0')) {
                return [];
            }

            return ['MessageHandlers must be final, so they cannot be extended/overwritten.'];
        }

        return [];
    }

    private function isMessageHandler(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->isAbstract()) {
            // abstract base classes should not be final
            return false;
        }

        foreach ($class->getInterfaces() as $interface) {
            if ($interface->getName() === MessageHandlerInterface::class) {
                return true;
            }
        }

        return false;
    }
}
