<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Console\Command\Command;

/**
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('core')]
class UseCLIContextRule implements Rule
{
    /**
     * @var array<class-string>
     */
    private array $baseClasses = [
        Command::class,
        ScheduledTaskHandler::class,
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->name !== 'createDefaultContext') {
            return [];
        }

        if (!$node->class instanceof Name || $node->class->toString() !== 'Shopware\Core\Framework\Context') {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        foreach ($this->baseClasses as $baseClass) {
            if ($classReflection->isSubclassOf($baseClass)) {
                return [
                    RuleErrorBuilder::message('Method Context::createDefaultContext() should not be used in CLI context. Use Context::createCLIContext() instead.')
                        ->line($node->getLine())
                        ->identifier('shopware.cliContext')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
