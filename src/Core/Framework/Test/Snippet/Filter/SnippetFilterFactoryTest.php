<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Snippet\Exception\FilterNotFoundException;
use Shopware\Core\Framework\Snippet\Filter\AuthorFilter;
use Shopware\Core\Framework\Snippet\Filter\CustomFilter;
use Shopware\Core\Framework\Snippet\Filter\EmptySnippetFilter;
use Shopware\Core\Framework\Snippet\Filter\NamespaceFilter;
use Shopware\Core\Framework\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\Framework\Snippet\Filter\TermFilter;
use Shopware\Core\Framework\Snippet\Filter\TranslationKeyFilter;

class SnippetFilterFactoryTest extends TestCase
{
    /**
     * @dataProvider dataProviderForTestGetFilter
     */
    public function testGetFilter($filterName, $expectedResult, $expectException): void
    {
        $factory = $this->getFactory();

        if ($expectException) {
            $this->expectException(FilterNotFoundException::class);
        }

        $result = $factory->getFilter($filterName);

        static::assertInstanceOf($expectedResult, $result);
    }

    public function dataProviderForTestGetFilter(): array
    {
        return [
            ['', null, true],
            ['foo', null, true],
            ['bar', null, true],
            ['author', AuthorFilter::class, false],
            ['custom', CustomFilter::class, false],
            ['empty', EmptySnippetFilter::class, false],
            ['namespace', NamespaceFilter::class, false],
            ['term', TermFilter::class, false],
            ['translationKey', TranslationKeyFilter::class, false],
        ];
    }

    private function getFactory(): SnippetFilterFactory
    {
        return new SnippetFilterFactory([
            new AuthorFilter(),
            new CustomFilter(),
            new EmptySnippetFilter(),
            new NamespaceFilter(),
            new TermFilter(),
            new TranslationKeyFilter(),
        ]);
    }
}
