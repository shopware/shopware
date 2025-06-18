<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\Danger\Rules;


use Danger\Context;
use Danger\Platform\Github\Github;
use Danger\Struct\Github\PullRequest;
use PHPUnit\Framework\TestCase;

use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingIntegrationTestInSplitSuite;

class MissingIntegrationTestInSplitSuiteTest extends TestCase
{
    public function testReturnNothingWhenNoTestFilesFound(): void
    {
        $context = $this->createMock(Context::class);
        $context->platform = $this->createMock(Github::class);
        $context->platform->pullRequest = $this->createMock(PullRequest::class);

        $rule = new MissingIntegrationTestInSplitSuite();

        $result = $rule($context);
        $this->assertEmpty($result, 'The rule should not throw an exception when executed.');
    }

    public function testReturnNothingWhen
}
