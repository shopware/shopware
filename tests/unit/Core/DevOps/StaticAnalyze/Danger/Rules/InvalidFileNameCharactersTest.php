<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\InvalidFileNameCharacters;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InvalidFileNameCharacters::class)]
class InvalidFileNameCharactersTest extends TestCase
{
    #[TestDox('Fails for file names outside alphanumerics, dots, dashes, underscores and slashes')]
    #[DataProvider('fileNameProvider')]
    public function testFileNameValidation(string $fileName, string $status, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status),
        ])));

        (new InvalidFileNameCharacters())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('invalid special characters', $context->getFailures()[0]);
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }
    }

    public static function fileNameProvider(): \Generator
    {
        yield 'plain php path passes' => ['src/Core/Framework/Framework.php', File::STATUS_ADDED, false];
        yield 'dashes, dots and underscores pass' => ['src/some-dir/my_file.v2.php', File::STATUS_ADDED, false];
        yield 'space in file name fails' => ['src/Core/My File.php', File::STATUS_ADDED, true];
        yield 'parentheses fail' => ['docs/adr/decision(1).md', File::STATUS_ADDED, true];
        yield 'umlauts fail' => ['src/Core/Überschrift.php', File::STATUS_ADDED, true];
        yield 'invalid name in a removed file passes, deletions clean up' => ['src/Core/My File.php', File::STATUS_REMOVED, false];
        yield 'the .run directory is exempt' => ['.run/My Run Config.run.xml', File::STATUS_ADDED, false];
    }
}
