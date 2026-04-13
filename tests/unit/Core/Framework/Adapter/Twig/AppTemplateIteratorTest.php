<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Adapter\Twig\AppTemplateIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(AppTemplateIterator::class)]
class AppTemplateIteratorTest extends TestCase
{
    use EnvTestBehaviour;

    public function testIteratorYieldsFilesystemAndDatabaseTemplates(): void
    {
        $filesystemTemplates = new \ArrayObject(['storefront/base.html.twig', 'storefront/page.html.twig']);

        $termsResult = new TermsResult('path-names', [
            new Bucket('storefront/app/index.html.twig', 1, null),
        ]);

        $aggregationResult = new AggregationResultCollection();
        $aggregationResult->add($termsResult);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('aggregate')->willReturn($aggregationResult);

        $iterator = new AppTemplateIterator($filesystemTemplates, $repository);

        $result = iterator_to_array($iterator, false);

        static::assertSame([
            'storefront/base.html.twig',
            'storefront/page.html.twig',
            'storefront/app/index.html.twig',
        ], $result);
    }

    public function testIteratorYieldsOnlyFilesystemTemplatesInDatabaselessMode(): void
    {
        $this->setEnvVars(['DATABASE_URL' => MySQLFactory::PLACEHOLDER_DATABASE_URL]);

        $filesystemTemplates = new \ArrayObject(['storefront/base.html.twig']);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('aggregate');

        $iterator = new AppTemplateIterator($filesystemTemplates, $repository);

        $result = iterator_to_array($iterator);

        static::assertSame(['storefront/base.html.twig'], $result);
    }

    public function testIteratorWithNoDatabaseTemplates(): void
    {
        $filesystemTemplates = new \ArrayObject(['storefront/base.html.twig']);

        $termsResult = new TermsResult('path-names', []);

        $aggregationResult = new AggregationResultCollection();
        $aggregationResult->add($termsResult);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('aggregate')->willReturn($aggregationResult);

        $iterator = new AppTemplateIterator($filesystemTemplates, $repository);

        $result = iterator_to_array($iterator);

        static::assertSame(['storefront/base.html.twig'], $result);
    }
}
