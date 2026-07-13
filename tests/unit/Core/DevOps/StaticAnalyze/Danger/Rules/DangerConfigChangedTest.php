<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\DangerConfigChanged;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[CoversClass(DangerConfigChanged::class)]
class DangerConfigChangedTest extends TestCase
{
    #[TestDox('Notices that .danger.php changes do not apply to the same pull request')]
    public function testNoticesWhenDangerConfigIsTouched(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile('.danger.php')])));

        (new DangerConfigChanged())($context);

        static::assertSame(
            ['Any changes to .danger.php will not be reflected in your pull request. Commit your changes separately.'],
            $context->getNotices()
        );
    }

    #[TestDox('Stays silent when .danger.php is untouched')]
    public function testSilentWithoutDangerConfigChange(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile('src/Core/Framework/Framework.php')])));

        (new DangerConfigChanged())($context);

        static::assertFalse($context->hasReports());
    }
}
