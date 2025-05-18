<?php

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<FuncCall>
 *
 * @internal
 */
#[Package('framework')]
class NoFileExistsRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        if (!$node->name instanceof Name) {
            return [];
        }

        // TODO: also check SymfonyFilesystem->exists calls

        if ($node->name->toString() === 'file_exists') {
            return [
                RuleErrorBuilder::message('Avoid using file_exists. It is inefficient, insecure, and cannot handle symlinks. Additionally, it cannot distinguish between files and directories. Use is_dir or is_file instead.')
                    ->identifier('shopware.file_exists')
                    ->build(),
            ];
        }

        return [];
    }

}