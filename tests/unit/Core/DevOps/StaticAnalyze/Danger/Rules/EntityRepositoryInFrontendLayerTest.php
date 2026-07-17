<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\EntityRepositoryInFrontendLayer;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[CoversClass(EntityRepositoryInFrontendLayer::class)]
#[RequiresMethod(File::class, 'getContent')]
class EntityRepositoryInFrontendLayerTest extends TestCase
{
    #[TestDox('Flags newly added EntityRepository usage in the Storefront frontend layer')]
    #[DataProvider('frontendFileProvider')]
    public function testDetectsNewRepositoryUsage(string $fileName, string $status, string $patch, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, '', $patch),
        ])));

        (new EntityRepositoryInFrontendLayer())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('Do not use direct repository calls in the Frontend Layer', $context->getFailures()[0]);
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }
    }

    public static function frontendFileProvider(): \Generator
    {
        yield 'new repository usage in a controller fails' => [
            'src/Storefront/Controller/ProductController.php',
            'modified',
            '+        private readonly EntityRepository $productRepository,',
            true,
        ];
        yield 'new repository usage in a page loader fails' => [
            'src/Storefront/Page/Product/ProductPageLoader.php',
            'modified',
            '+        $this->repository = new EntityRepository();',
            true,
        ];
        yield 'new repository usage in a pagelet fails' => [
            'src/Storefront/Pagelet/Menu/MenuPageletLoader.php',
            'modified',
            '+        private readonly EntityRepository $categoryRepository,',
            true,
        ];
        yield 'removed repository usage passes' => [
            'src/Storefront/Controller/ProductController.php',
            'modified',
            '-        private readonly EntityRepository $productRepository,',
            false,
        ];
        yield 'added usage on a line mentioning a deprecation passes' => [
            'src/Storefront/Controller/ProductController.php',
            'modified',
            '+     * @deprecated tag:v6.8.0 - EntityRepository usage will be removed',
            false,
        ];
        yield 'repository usage outside the frontend layer passes' => [
            'src/Storefront/Framework/Routing/Router.php',
            'modified',
            '+        private readonly EntityRepository $repository,',
            false,
        ];
        yield 'added files are not checked, only modified ones' => [
            'src/Storefront/Controller/NewController.php',
            'added',
            '+        private readonly EntityRepository $repository,',
            false,
        ];
    }
}
