<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\MissingConstantFromReflectionException;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('core')]
class ExtensionRule implements Rule
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
        $example = $this->isExample($node);

        $extension = $this->isExtension($node);

        $internal = $this->isInternal($node);

        if (!$extension && !$example) {
            return [];
        }

        $errors = [];
        if ($internal) {
            $errors[] = [
                RuleErrorBuilder::message('Extension / Example classes should not be marked as internal')
                    ->line($node->getDocComment()?->getStartLine() ?? 0)
                    ->build(),
            ];
        }

        if ($extension) {
            $errors = array_merge($errors, $this->validateExtension($node));
        }

        return $errors;
    }

    /**
     * @return array<array-key, RuleError|string>
     */
    private function validateExtension(InClassNode $node): array
    {
        $errors = [];

        $nameConstant = null;
        try {
            $nameConstant = $node->getClassReflection()->getConstant('NAME');
        } catch (MissingConstantFromReflectionException) {
            $errors[] = [
                RuleErrorBuilder::message('Extension classes should have a public NAME constant')
                    ->line($node->getLine())
                    ->build(),
            ];
        }

        if ($nameConstant && !$nameConstant->isPublic()) {
            $errors[] = [
                RuleErrorBuilder::message('Extension classes should have a public NAME constant')
                    ->line($node->getLine())
                    ->build(),
            ];
        }

        return $errors;
    }

    private function isInternal(InClassNode $node): bool
    {
        $doc = $node->getDocComment()?->getText() ?? '';

        return \str_contains($doc, '@internal') || \str_contains($doc, 'reason:becomes-internal');
    }

    private function isExtension(InClassNode $node): bool
    {
        $reflection = $node->getClassReflection();

        if ($reflection->getParentClass() === null) {
            return false;
        }

        $parentClass = $reflection->getParentClass()->getName();

        return $parentClass === Extension::class;
    }

    private function isExample(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        return \str_contains($namespace, 'Shopware\\Tests\\Examples\\');
    }
}
