<?php declare(strict_types=1);

namespace Shopware\Docs\Test\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Docs\Command\ConvertMarkdownDocsCommand;
//use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

class ConvertMarkdownDocsCommandTest extends TestCase
{
    public function testMetadataIsGeneratedForEmptyFiles(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $metadata = $commandTester->gatherMetadata([__FILE__ => '--Nothing to see here, move along--']);
        static::assertCount(1, $metadata);
        static::assertArrayHasKey(__FILE__, $metadata);
        static::assertEmpty($metadata[__FILE__]);
    }

    /**
     * @depends testMetadataIsGeneratedForEmptyFiles
     */
    public function testShouldExtractMetadataTags(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $metadata = $commandTester->gatherMetadata([__FILE__ => "[titleEn]: <>(A)\n"
            . "[titleDe]: <>(B)\t", //trailing whitespaces are allowed
        ]);

        static::assertNotEmpty($metadata);
        static::assertArrayHasKey(__FILE__, $metadata);

        $data = $metadata[__FILE__];
        static::assertArrayHasKey('titleEn', $data);
        static::assertEquals('A', $data['titleEn']);

        static::assertArrayHasKey('titleEn', $data);
        static::assertEquals('B', $data['titleDe']);
    }

    /**
     * @depends testMetadataIsGeneratedForEmptyFiles
     */
    public function testShouldNotExtractInvalidMetadataTags(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $metadata = $commandTester->gatherMetadata([__FILE__ => "[titleEn]: <>\n" // missing tag value
            . "[titleEn]: <>(\n)\n" // tag values cannot stretch multiple lines
            . "\t[titleEn]: <>(A)\n" // tag must start with on a newline
            . "[titleEn]: <>(A) somemorecontent\n" // line must end after tag value
            . "[titleEn]: (['A'])\n" // missing diamond
            . "[titleEn] <>(['A'])", // missing colon
        ]);

        static::assertArrayHasKey(__FILE__, $metadata);
        static::assertEmpty($metadata[__FILE__]);
    }

    public function testShouldUseRootPathInternally(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $data = ['/tmp/a.md' => 'My _awesome_ markdown!'];
        $converted = $commandTester->convertMarkdownFiles($data, [], '/tmp/');

        static::assertNotEmpty($converted);
        static::assertTrue(key_exists('/a', $converted));
    }

    /**
     * @depends testShouldExtractMetadataTags
     */
    public function testShouldReplaceRelativeFileLinks(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $fileA = realpath(__DIR__ . '/Fixtures/relFileLinksA.md');
        $fileB = realpath(__DIR__ . '/Fixtures/relFileLinksB.md');

        $data = [
            $fileA => file_get_contents($fileA),
            $fileB => file_get_contents($fileB),
        ];

        $metadata = $commandTester->gatherMetadata($data);
        static::assertCount(2, array_keys($metadata));
        $converted = $commandTester->convertMarkdownToHtml($data[$fileA], $fileA, $metadata);

        static::assertStringContainsString('href="bing.de"', $converted);
    }

    /**
     * @depends testShouldReplaceRelativeFileLinks
     */
    public function testShouldAppendAnchorsToRelativeLinks(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $fileA = realpath(__DIR__ . '/Fixtures/relFileLinksA.md');
        $fileB = realpath(__DIR__ . '/Fixtures/relFileLinksB.md');

        $data = [
            $fileA => file_get_contents($fileA),
            $fileB => file_get_contents($fileB),
        ];

        $metadata = $commandTester->gatherMetadata($data);
        static::assertCount(2, array_keys($metadata));
        $converted = $commandTester->convertMarkdownToHtml($data[$fileB], $fileB, $metadata);

        static::assertStringContainsString('href="google.de#some-awesome-headliner"', $converted);
    }

    public function testShouldBuildWikiUrlFromTitletag()
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $fileA = realpath(__DIR__ . '/Fixtures/relFileLinksA.md');
        $fileB = realpath(__DIR__ . '/Fixtures/relFileLinksB.md');

        $data = [
            $fileA => file_get_contents($fileA),
            $fileB => file_get_contents($fileB),
        ];

        $metadata = $commandTester->gatherMetadata($data);
        $metadata = $commandTester->enrichMetadata($metadata, __DIR__ . '/Fixtures/', '/base/url');
        static::assertEquals($metadata[$fileA]['wikiUrl'], '/base/url-en/great-title');

        $converted = $commandTester->convertMarkdownToHtml($data[$fileB], $fileB, $metadata);
        static::assertStringContainsString('href="/base/url-en/great-title#some-awesome-headliner"', $converted);
    }

    public function testShouldNotModifyLocalAnchors(): void
    {
        $fileA = realpath(__DIR__ . '/Fixtures/relFileLinksA.md');

        $commandTester = new ConvertMarkdownDocsCommand();

        $data = [
            $fileA => file_get_contents($fileA),
        ];

        $metadata = $commandTester->gatherMetadata($data);
        $metadata = $commandTester->enrichMetadata($metadata, __DIR__ . '/Fixtures/', '/base/url');
        $converted = $commandTester->convertMarkdownToHtml($data[$fileA], $fileA, $metadata);
        static::assertStringContainsString('href="#some-awesome-headliner"', $converted);
    }

    public function testUnknownTagsCreateWarning(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $commandTester->checkMetadata([
            'A' => ['titleEn' => 'HelloWorld', 'wusel' => 'dusel'],
        ]);

        $warnings = $commandTester->getWarningStack();

        static::assertNotEmpty($warnings);
        $unknownTagWarning = $warnings[0];

        static::assertStringContainsStringIgnoringCase('unknown metatag', $unknownTagWarning);
        static::assertStringContainsStringIgnoringCase('"wusel" in file A', $unknownTagWarning);
    }

    public function testHappyCaseMetadataCreatesNoWarning(): void
    {
        $tags = [];
        $commandTester = new ConvertMarkdownDocsCommand();
        foreach (ConvertMarkdownDocsCommand::REQUIRED_METATAGS as $tag) {
            $tags[$tag] = 'A';
        }

        $commandTester->checkMetadata([
            'A' => $tags,
        ]);

        $warnings = $commandTester->getWarningStack();

        static::assertEmpty($warnings);
    }

    public function testMissingTitleEnCreatesWarning(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $commandTester->checkMetadata([
            '__LongUniqueFileName__' => [],
        ]);

        $errors = $commandTester->getErrorStack();

        static::assertCount(1, $errors);
        $missingTagWarning = $errors[0];

        static::assertStringContainsString('"titleEn" in', $missingTagWarning);
        static::assertStringContainsString('__LongUniqueFileName__', $missingTagWarning);
    }

    public function testRedefinitionOfMetatagsCreateWarnings(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $metadata = $commandTester->gatherMetadata(
            [
                __FILE__ => "[titleEn]: <>(A)\n[titleEn]: <>(B)\n",
            ]
        );

        static::assertArrayHasKey(__FILE__, $metadata);
        $metadataOfFile = $metadata[__FILE__];
        static::assertCount(1, $metadataOfFile);
        static::assertArrayHasKey('titleEn', $metadataOfFile);
        static::assertEquals('B', $metadataOfFile['titleEn']);

        $warnings = $commandTester->getWarningStack();
        static::assertCount(1, $warnings);
        static::assertStringContainsString('multiple definitions of the same metatag', $warnings[0]);
        static::assertStringContainsString(__FILE__, $warnings[0]);
    }

    public function testMetatagsAreInvisibleInHtml(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $filename = __DIR__ . '/Fixtures/relFileLinksA.md';
        $converted = $commandTester->processFiles(
            [$filename => "[titleEn]: <>(Hello World)\n--Nothing to see here, move along--"],
            __DIR__ . '/Fixtures/',
            '/base'
        );

        static::assertStringNotContainsString('[titleEn]', $converted['/relFileLinksA']['content']);
    }

    public function testHtmlCodeblockIsEscaped(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $content = '```'
            . '<a href="google.de">LINKS<a/>'
            . '```'
            . 'This is some valid text';

        $metadata = ['titleEn' => 'Hello World'];
        $converted = $commandTester->convertMarkdownToHtml(
            $content,
            'A', $metadata
        );

        static::assertStringContainsString('This is some valid text', $converted);
        static::assertStringContainsString('LINKS', $converted);
        static::assertStringNotContainsString('<a/>', $converted);
    }

    public function testWikiUrlIsAutogenerated(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $filename = __DIR__ . '/Fixtures/relFileLinksA.md';
        $metadata = $commandTester->enrichMetadata([
            $filename => ['titleEn' => 'titel'],
        ],
            realpath(__DIR__ . '/'),
            '/'
        );

        static::assertNotEmpty($metadata);
        static::assertArrayHasKey($filename, $metadata);
        $data = $metadata[$filename];
        static::assertArrayHasKey('wikiUrl', $data);
    }

    public function testBlacklistedItemsAreRemoved(): void
    {
        $commandTester = new ConvertMarkdownDocsCommand();

        $files = [
            './my/awesome/file.md',
            './my/awesome/super.md',
            './my/awesome/awesome.md',
            './my/awesome/wusel.md',
        ];

        $conditions = [
            "/.*wusel\.md/",
            "/.*super\.md/",
        ];

        $result = $commandTester->removeBlacklistedFiles($files, $conditions);

        static::assertCount(2, $result);
        static::assertContains('./my/awesome/file.md', $result);
        static::assertContains('./my/awesome/awesome.md', $result);
    }

    public function testCompleteConversionProcess(): void
    {
        $outPath = sys_get_temp_dir() . '/docconv/';
        $srcPath = realpath(__DIR__ . '/');
        $commandTester = new CommandTester(new ConvertMarkdownDocsCommand());
        $commandTester->execute(['--input' => $srcPath, '--output' => $outPath]);

        static::assertFileExists($outPath . 'Fixtures/relFileLinksA.html');
        static::assertFileExists($outPath . 'Fixtures/relFileLinksB.html');
    }
}
