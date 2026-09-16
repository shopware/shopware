<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Shopware\Tests\Unit\Core\Framework\SomeUnitTest;

/**
 * @codeCoverageIgnore
 *
 * @see SomeUnitTest
 */
class SeeUnitTestClass
{
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
