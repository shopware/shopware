<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Changelog;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Changelog\ChangelogParser;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
class ChangelogParserTest extends TestCase
{
    use ChangelogTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @return list<array{0: string, 1: array<string, string|null>, 2: string, 3: int}>
     */
    public static function provide(): array
    {
        return [
            [
                __DIR__ . '/_fixture/stage/minimal-template.txt',
                [
                    'title' => '',
                    'issue' => '',
                    'flag' => null,
                    'author' => null,
                    'authorEmail' => null,
                    'authorGithub' => null,
                    'core' => null,
                    'storefront' => null,
                    'admin' => null,
                    'api' => null,
                    'upgrade' => null,
                    'major' => null,
                ],
                __DIR__ . '/_fixture/stage/minimal-template-expectation.txt',
                3,
            ],
            [
                __DIR__ . '/_fixture/stage/full-template.txt',
                [
                    'title' => '_TITLE_',
                    'issue' => 'NEXT-1111',
                    'flag' => '_FLAG_',
                    'author' => '_AUTHOR_',
                    'authorEmail' => '_MAIL_',
                    'authorGithub' => '_GITHUB_',
                    'core' => "* Changed core\n  * changes",
                    'storefront' => "* Added store\n  * front\n  * list",
                    'admin' => "* Deprecated admin\n  * list",
                    'api' => "* Removed api\n  * infos",
                    'upgrade' => "## UPGRADE\n### THE INFORMATION",
                    'major' => "## DO THIS:\n\n* FOO",
                ],
                __DIR__ . '/_fixture/stage/full-template-expectation.txt',
                1,
            ],
        ];
    }

    /**
     * @param array<string, string|null> $expectedData
     */
    #[DataProvider('provide')]
    public function testData(string $inFile, array $expectedData, string $outFile, int $expectedExceptions): void
    {
        $parser = self::getContainer()->get(ChangelogParser::class);

        $logEntry = $parser->parse(new SplFileInfo($inFile, __DIR__, __DIR__), __DIR__);

        static::assertSame($expectedData['title'], $logEntry->getTitle());
        static::assertSame($expectedData['issue'], $logEntry->getIssue());
        static::assertSame($expectedData['flag'], $logEntry->getFlag());
        static::assertSame($expectedData['author'], $logEntry->getAuthor());
        static::assertSame($expectedData['authorEmail'], $logEntry->getAuthorEmail());
        static::assertSame($expectedData['authorGithub'], $logEntry->getAuthorGitHub());
        static::assertSame($expectedData['core'], $logEntry->getCore());
        static::assertSame($expectedData['storefront'], $logEntry->getStorefront());
        static::assertSame($expectedData['admin'], $logEntry->getAdministration());
        static::assertSame($expectedData['api'], $logEntry->getApi());
        static::assertSame($expectedData['upgrade'], $logEntry->getUpgradeInformation());
        static::assertSame($expectedData['major'], $logEntry->getNextMajorVersionChanges());
        $lines = file($outFile);
        static::assertIsArray($lines);

        /** @var array<string> $templateLines */
        $templateLines = explode(\PHP_EOL, $logEntry->toTemplate());

        foreach ($lines as $index => $line) {
            static::assertSame(trim($line), trim($templateLines[$index]));
        }

        $result = self::getContainer()->get(ValidatorInterface::class)->validate($logEntry);

        static::assertCount($expectedExceptions, $result);
    }
}
