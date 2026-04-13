<?php declare(strict_types=1);

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
    public const FILE_EXISTS_INFORMATION = 'The method file_exists is inefficient. Additionally, it cannot distinguish between files and directories. Use is_dir or is_file instead.';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toString() !== 'file_exists') {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('Avoid using file_exists. %s', self::FILE_EXISTS_INFORMATION))
                ->identifier('shopware.fileExists')
                ->build(),
        ];
    }
}
