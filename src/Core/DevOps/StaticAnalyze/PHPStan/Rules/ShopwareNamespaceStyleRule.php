<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<FileNode>
 *
 * @internal
 */
#[Package('core')]
class ShopwareNamespaceStyleRule implements Rule
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $namespaceNode = null;

        foreach ($node->getNodes() as $subNode) {
            if ($subNode instanceof Namespace_) {
                $namespaceNode = $subNode;

                break;
            }
        }

        if ($namespaceNode === null) {
            return [];
        }

        $namespaceParts = $namespaceNode->name?->getParts() ?: [];

        if (\count($namespaceParts) > 0 && $namespaceParts[0] !== 'Shopware') {
            return [
                RuleErrorBuilder::message('Namespace must start with Shopware')
                    ->line($namespaceNode->getLine())
                    ->identifier('shopware.namespace')
                    ->build(),
            ];
        }

        if (\count($namespaceParts) < 3) {
            return [];
        }

        if ($namespaceParts[2] === 'Command') {
            return [
                RuleErrorBuilder::message('No global Command directories allowed, put your commands in the right domain directory')
                    ->line($namespaceNode->getLine())
                    ->identifier('shopware.namespace')
                    ->build(),
            ];
        }

        if ($namespaceParts[2] === 'Exception') {
            return [
                RuleErrorBuilder::message('No global Exception directories allowed, put your exceptions in the right domain directory')
                    ->line($namespaceNode->getLine())
                    ->identifier('shopware.namespace')
                    ->build(),
            ];
        }

        return [];
    }
}
