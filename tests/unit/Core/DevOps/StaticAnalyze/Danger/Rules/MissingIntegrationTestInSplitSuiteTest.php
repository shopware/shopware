<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingIntegrationTestInSplitSuite;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingIntegrationTestInSplitSuite::class)]
class MissingIntegrationTestInSplitSuiteTest extends TestCase
{
    private const FIXTURE_CONFIG = __DIR__ . '/_fixtures/phpunit.xml.dist';

    #[TestDox('Fails when a Core Framework integration test lives outside every split-suite entry')]
    #[DataProvider('integrationTestProvider')]
    public function testSplitSuiteCoverage(string $fileName, bool $expectFailure, string $expectedMissingEntry = ''): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, File::STATUS_ADDED),
        ])));

        (new MissingIntegrationTestInSplitSuite(self::FIXTURE_CONFIG))($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('core-batch testsuite of phpunit.xml.dist', $context->getFailures()[0]);
            static::assertStringContainsString(htmlentities($expectedMissingEntry), $context->getFailures()[0]);
        }
    }

    public static function integrationTestProvider(): \Generator
    {
        yield 'test in a listed batch directory passes' => [
            'tests/integration/Core/Framework/Api/Controller/SyncControllerTest.php',
            false,
        ];
        yield 'test in an unlisted domain directory fails with the directory to add' => [
            'tests/integration/Core/Framework/Webhook/WebhookDispatchTest.php',
            true,
            '<directory>tests/integration/Core/Framework/Webhook</directory>',
        ];
        yield 'listed top-level test file passes' => [
            'tests/integration/Core/Framework/VersioningTest.php',
            false,
        ];
        yield 'unlisted top-level test file fails with the file to add' => [
            'tests/integration/Core/Framework/NewRootTest.php',
            true,
            '<file>tests/integration/Core/Framework/NewRootTest.php</file>',
        ];
        yield 'integration test outside Core Framework passes' => [
            'tests/integration/Core/Checkout/Cart/CartPersisterTest.php',
            false,
        ];
        yield 'unit test is not checked' => [
            'tests/unit/Core/Framework/Webhook/WebhookTest.php',
            false,
        ];
    }

    #[TestDox('Fails with a config hint when the phpunit config cannot be loaded')]
    public function testFailsForUnloadablePhpunitConfig(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('tests/integration/Core/Framework/Api/Controller/SyncControllerTest.php', File::STATUS_ADDED),
        ])));

        // DOMDocument::load() emits an E_WARNING for the missing file; swallow it locally
        set_error_handler(static fn (): bool => true, \E_WARNING);
        try {
            (new MissingIntegrationTestInSplitSuite(__DIR__ . '/_fixtures/does-not-exist.xml'))($context);
        } finally {
            restore_error_handler();
        }

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString('Was not able to load phpunit config file', $context->getFailures()[0]);
    }

    #[TestDox('A phpunit.xml.dist changed in the pull request is used for the check')]
    public function testPhpunitConfigFromPullRequestWins(): void
    {
        $prConfig = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <testsuites>
                    <testsuite name="core-framework-batch1">
                        <directory>tests/integration/Core/Framework/Webhook</directory>
                    </testsuite>
                </testsuites>
            </phpunit>
            XML;

        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('tests/integration/Core/Framework/Webhook/WebhookDispatchTest.php', File::STATUS_ADDED),
            new StubFile('phpunit.xml.dist', File::STATUS_MODIFIED, $prConfig),
        ])));

        (new MissingIntegrationTestInSplitSuite(self::FIXTURE_CONFIG))($context);

        static::assertFalse($context->hasFailures());
    }
}
