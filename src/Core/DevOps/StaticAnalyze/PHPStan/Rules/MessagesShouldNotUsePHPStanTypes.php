<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
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
#[Package('framework')]
class MessagesShouldNotUsePHPStanTypes implements Rule
{
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

        $errors = [];
        foreach (explode("\n", $classDocComment->getText()) as $line => $text) {
            foreach (['@phpstan-type', '@phpstan-import-type'] as $annotation) {
                if (!str_contains($text, $annotation)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(\sprintf('Messages should not use %s annotations', $annotation))
                    ->identifier('shopware.messagePHPStanAnnotation')
                    ->line($classDocComment->getStartLine() + $line)
                    ->build();
            }
        }

        return $errors;
    }
}
