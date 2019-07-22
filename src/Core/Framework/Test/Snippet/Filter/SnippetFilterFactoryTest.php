<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Snippet\Exception\FilterNotFoundException;
use Shopware\Core\Framework\Snippet\Filter\AddedFilter;
use Shopware\Core\Framework\Snippet\Filter\AuthorFilter;
use Shopware\Core\Framework\Snippet\Filter\EditedFilter;
use Shopware\Core\Framework\Snippet\Filter\EmptySnippetFilter;
use Shopware\Core\Framework\Snippet\Filter\NamespaceFilter;
use Shopware\Core\Framework\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\Framework\Snippet\Filter\TermFilter;
use Shopware\Core\Framework\Snippet\Filter\TranslationKeyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class SnippetFilterFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider dataProviderForTestGetFilter
     */
    public function testGetFilter($filterName, $expectedResult, $expectException): void
    {
        $factory = $this->getContainer()->get(SnippetFilterFactory::class);

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
            ['edited', EditedFilter::class, false],
            ['empty', EmptySnippetFilter::class, false],
            ['namespace', NamespaceFilter::class, false],
            ['term', TermFilter::class, false],
            ['translationKey', TranslationKeyFilter::class, false],
            ['added', AddedFilter::class, false],
        ];
    }
}
