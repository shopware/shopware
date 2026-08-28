<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\SqlHeredocUsage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SqlHeredocUsage::class)]
class SqlHeredocUsageTest extends TestCase
{
    #[TestDox('Fails for newly added SQL heredocs, ignores nowdocs, removals and unmodified files')]
    #[DataProvider('patchProvider')]
    public function testHeredocDetection(string $fileName, string $status, string $patch, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, '', $patch),
        ])));

        (new SqlHeredocUsage())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('Nowdoc', $context->getFailures()[0]);
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }
    }

    public static function patchProvider(): \Generator
    {
        yield 'added SQL heredoc fails' => [
            'src/Core/Framework/Dal/Indexer.php',
            File::STATUS_MODIFIED,
            "+        \$sql = <<<SQL\n+SELECT 1;\n+SQL;",
            true,
        ];
        yield 'added SQL nowdoc passes' => [
            'src/Core/Framework/Dal/Indexer.php',
            File::STATUS_MODIFIED,
            "+        \$sql = <<<'SQL'\n+SELECT 1;\n+SQL;",
            false,
        ];
        yield 'removed SQL heredoc passes' => [
            'src/Core/Framework/Dal/Indexer.php',
            File::STATUS_MODIFIED,
            '-        $sql = <<<SQL',
            false,
        ];
        yield 'heredoc in an added file passes, only modified files are checked' => [
            'src/Core/Framework/Dal/NewIndexer.php',
            File::STATUS_ADDED,
            '+        $sql = <<<SQL',
            false,
        ];
        yield 'heredoc in the danger config itself is excluded' => [
            '.danger.php',
            File::STATUS_MODIFIED,
            '+        $sql = <<<SQL',
            false,
        ];
    }
}
