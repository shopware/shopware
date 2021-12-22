<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Changelog;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Changelog\ChangelogParser;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChangelogParserTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChangelogTestBehaviour;

    public function provide(): array
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
                    'issue' => '_ISSUE_',
                    'flag' => '_FLAG_',
                    'author' => '_AUTHOR_',
                    'authorEmail' => '_MAIL_',
                    'authorGithub' => '_GITHUB_',
                    'core' => "* core\n* changes",
                    'storefront' => "* store\n* front\n* list",
                    'admin' => "* admin\n* list",
                    'api' => "* api\n* infos",
                    'upgrade' => "## UPGRADE\n### THE INFORMATION",
                    'major' => "## DO THIS:\n\n* FOO",
                ],
                __DIR__ . '/_fixture/stage/full-template-expectation.txt',
                1,
            ],
        ];
    }

    /**
     * @dataProvider provide
     */
    public function testData(string $inFile, array $expecvtedData, string $outFile, int $expectedExceptions): void
    {
        $parser = $this
            ->getContainer()
            ->get(ChangelogParser::class);

        $logEntry = $parser->parse(file_get_contents($inFile));

        static::assertSame($expecvtedData['title'], $logEntry->getTitle());
        static::assertSame($expecvtedData['issue'], $logEntry->getIssue());
        static::assertSame($expecvtedData['flag'], $logEntry->getFlag());
        static::assertSame($expecvtedData['author'], $logEntry->getAuthor());
        static::assertSame($expecvtedData['authorEmail'], $logEntry->getAuthorEmail());
        static::assertSame($expecvtedData['authorGithub'], $logEntry->getAuthorGitHub());
        static::assertSame($expecvtedData['core'], $logEntry->getCore());
        static::assertSame($expecvtedData['storefront'], $logEntry->getStorefront());
        static::assertSame($expecvtedData['admin'], $logEntry->getAdministration());
        static::assertSame($expecvtedData['api'], $logEntry->getApi());
        static::assertSame($expecvtedData['upgrade'], $logEntry->getUpgradeInformation());
        static::assertSame($expecvtedData['major'], $logEntry->getNextMajorVersionChanges());
        $lines = file($outFile);
        $templateLines = explode(\PHP_EOL, $logEntry->toTemplate());

        foreach ($lines as $index => $line) {
            static::assertSame(trim($line), trim($templateLines[$index]));
        }

        $result = $this->getContainer()->get(ValidatorInterface::class)->validate($logEntry);

        static::assertSame($expectedExceptions, $result->count());
    }
}
