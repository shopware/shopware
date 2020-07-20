<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Files;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileCollectionFactory;
use Shopware\Core\System\Snippet\Files\SnippetFileLoaderInterface;
use Shopware\Core\System\Test\Snippet\Mock\MockSnippetFile;

class SnippetFileCollectionFactoryTest extends TestCase
{
    public function testCreateSnippetFileCollection(): void
    {
        $snippetFileLoaderMock = $this->createMock(SnippetFileLoaderInterface::class);
        $snippetFileLoaderMock->expects(static::once())
            ->method('loadSnippetFilesIntoCollection')
            ->willReturnCallback(function (SnippetFileCollection $fileCollection): void {
                $fileCollection->add(new MockSnippetFile('storefront.de-DE', 'de-DE', '{}', true));
            });

        $factory = new SnippetFileCollectionFactory([], $snippetFileLoaderMock);

        $collection = $factory->createSnippetFileCollection();

        static::assertCount(1, $collection);
    }
}
