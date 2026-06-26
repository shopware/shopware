<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\CodeCoverageIgnoreEvaluationRuleTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see CodeCoverageIgnoreEvaluationRuleTest
 */
#[Package('framework')]
final class Errors
{
    private function __construct()
    {
    }

    public static function classLevel(string $className, string $methodName, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(\sprintf(
            'Class %s is annotated @codeCoverageIgnore but method %s() contains logic. Remove the annotation, extract the logic to a covered class, or add a @see pointing to an existing integration test that exercises it.',
            $className,
            $methodName,
        ))
            ->identifier('shopware.codeCoverageIgnoreOnLogic')
            ->line($line)
            ->build();
    }

    public static function methodLevel(string $className, string $methodName, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(\sprintf(
            'Method %s::%s() is annotated @codeCoverageIgnore but contains logic. Remove the annotation, extract the logic to a covered method, or add a @see pointing to an existing integration test that exercises it.',
            $className,
            $methodName,
        ))
            ->identifier('shopware.codeCoverageIgnoreOnLogic')
            ->line($line)
            ->build();
    }
}
