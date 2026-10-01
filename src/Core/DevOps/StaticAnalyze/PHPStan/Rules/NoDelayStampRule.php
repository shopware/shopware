<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @implements Rule<New_>
 *
 * @internal
 */
#[Package('framework')]
class NoDelayStampRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        if ($node->class->toString() !== DelayStamp::class) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Usage of DelayStamp is not allowed, as it is not compatible with all messenger transports.')
                ->identifier('shopware.noDelayStamp')
                ->build(),
        ];
    }
}
