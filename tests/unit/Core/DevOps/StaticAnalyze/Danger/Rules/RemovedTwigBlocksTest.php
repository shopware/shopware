<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\RemovedTwigBlocks;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RemovedTwigBlocks::class)]
class RemovedTwigBlocksTest extends TestCase
{
    #[TestDox('Warns about twig blocks that are removed from a template without being re-added')]
    #[DataProvider('patchProvider')]
    public function testRemovedBlockDetection(string $patch, bool $expectWarning, string $expectedBlock = ''): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('src/Storefront/Resources/views/storefront/page/product-detail/index.html.twig', File::STATUS_MODIFIED, '', $patch),
        ])));

        (new RemovedTwigBlocks())($context);

        static::assertSame($expectWarning, $context->hasWarnings());
        if ($expectWarning) {
            static::assertStringContainsString('moved or deleted a twig block', $context->getWarnings()[0]);
            static::assertStringContainsString($expectedBlock, $context->getWarnings()[0]);
        }
    }

    public static function patchProvider(): \Generator
    {
        yield 'deleted block warns' => [
            "-{% block page_product_detail_buy %}\n-{% endblock %}",
            true,
            'page_product_detail_buy',
        ];
        // array_diff_assoc compares by position: removal at index 0 vs addition at index 0 match
        yield 'block removed and re-added at the same diff position passes' => [
            "-{% block page_product_detail_buy %}\n+{% block page_product_detail_buy %}",
            false,
        ];
        yield 'content-only change without block markers passes' => [
            "-    <p>old</p>\n+    <p>new</p>",
            false,
        ];
        yield 'purely added block passes' => [
            '+{% block page_product_detail_new %}',
            false,
        ];
    }

    #[TestDox('Only modified Storefront templates are inspected')]
    public function testNonTemplateFilesAreIgnored(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('src/Storefront/Controller/ProductController.php', File::STATUS_MODIFIED, '', '-{% block foo %}'),
            new StubFile('src/Storefront/Resources/views/storefront/base.html.twig', File::STATUS_ADDED, '', '-{% block bar %}'),
        ])));

        (new RemovedTwigBlocks())($context);

        static::assertFalse($context->hasReports());
    }
}
