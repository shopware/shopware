<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Filter;

use Shopware\Core\Framework\Snippet\Filter\AuthorFilter;
use Shopware\Core\Framework\Snippet\Filter\CustomFilter;
use Shopware\Core\Framework\Snippet\Filter\EmptySnippetFilter;
use Shopware\Core\Framework\Snippet\Filter\NamespaceFilter;
use Shopware\Core\Framework\Snippet\Filter\SnippetFilterInterface;
use Shopware\Core\Framework\Snippet\Filter\TermFilter;
use Shopware\Core\Framework\Snippet\Filter\TranslationKeyFilter;
use Symfony\Bundle\TwigBundle\Tests\TestCase;

class FilterTest extends TestCase
{
    public function testGetFiltersName()
    {
        $authorFilter = new AuthorFilter();
        $customFilter = new CustomFilter();
        $emptyFilter = new EmptySnippetFilter();
        $namespaceFilter = new NamespaceFilter();
        $termFilter = new TermFilter();
        $keyFilter = new TranslationKeyFilter();

        $this->assertSame('author', $authorFilter->getName());
        $this->assertSame('custom', $customFilter->getName());
        $this->assertSame('empty', $emptyFilter->getName());
        $this->assertSame('namespace', $namespaceFilter->getName());
        $this->assertSame('term', $termFilter->getName());
        $this->assertSame('translationKey', $keyFilter->getName());
    }

    /**
     * @dataProvider dataProviderForTestFiltersSupports
     */
    public function testFiltersSupports(SnippetFilterInterface $filter, $filterName, $expectedResult)
    {
        $this->assertSame($expectedResult, $filter->supports($filterName));
    }

    public function dataProviderForTestFiltersSupports(): array
    {
        return [
            [new AuthorFilter(), '', false],
            [new AuthorFilter(), 'foo', false],
            [new AuthorFilter(), 'author', true],

            [new CustomFilter(), '', false],
            [new CustomFilter(), 'foo', false],
            [new CustomFilter(), 'custom', true],

            [new EmptySnippetFilter(), '', false],
            [new EmptySnippetFilter(), 'foo', false],
            [new EmptySnippetFilter(), 'empty', true],

            [new NamespaceFilter(), '', false],
            [new NamespaceFilter(), 'foo', false],
            [new NamespaceFilter(), 'namespace', true],

            [new TermFilter(), '', false],
            [new TermFilter(), 'foo', false],
            [new TermFilter(), 'term', true],

            [new TranslationKeyFilter(), '', false],
            [new TranslationKeyFilter(), 'foo', false],
            [new TranslationKeyFilter(), 'translationKey', true],
        ];
    }

    /**
     * @dataProvider dataProviderForTestSnippetsFilter
     */
    public function testSnippetsFilter(SnippetFilterInterface $filter, $params, $additionalData, $expectedResult)
    {
        $snippets = require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php';

        $this->assertArraySubset($expectedResult, $filter->filter($snippets, $params, $additionalData));
    }

    public function dataProviderForTestSnippetsFilter(): array
    {
        return [
            [new AuthorFilter(), [], [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new AuthorFilter(), ['notValidAuthor'], [], []],
            [new AuthorFilter(), ['user/admin'], [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultAuthorFilter1.php'],
            [new AuthorFilter(), ['user/fooBar'], [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultAuthorFilter2.php'],

            [new CustomFilter(), false, [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new CustomFilter(), false, ['customAuthors' => ['notValidAuthor']], []],
            [new CustomFilter(), true, ['customAuthors' => ['notValidAuthor']], []],
            [new CustomFilter(), true, ['customAuthors' => ['user/admin']], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultAuthorFilter1.php'],
            [new CustomFilter(), true, ['customAuthors' => ['user/fooBar']], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultAuthorFilter2.php'],

            [new EmptySnippetFilter(), false, [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new EmptySnippetFilter(), true, [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultEmptySnippetFilter.php'],

            [new NamespaceFilter(), [], [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new NamespaceFilter(), ['meta', 'detail'], [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultNamespaceFilter1.php'],
            [new NamespaceFilter(), ['documents'], [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultNamespaceFilter2.php'],
            [new NamespaceFilter(), ['fooBar'], [], []],

            [new TermFilter(), null, [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new TermFilter(), '', [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new TermFilter(), 'test', [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultTermFilter1.php'],
            [new TermFilter(), 'the r', [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultTermFilter2.php'],
            [new TermFilter(), 'foo bar test', [], []],

            [new TranslationKeyFilter(), null, [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new TranslationKeyFilter(), [], [], require __DIR__ . '/../_fixtures/testFilterSnippets/snippetsToFilter.php'],
            [new TranslationKeyFilter(), ['frontend.newsletter.index.sNewsletterInfo'], [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultTranslationKeyFilter1.php'],
            [new TranslationKeyFilter(), ['frontend.newsletter.index.sNewsletterInfo', 'widgets.recommendation.slide_articles.reducedPrice'], [], require __DIR__ . '/../_fixtures/testFilterSnippets/expectedResultTranslationKeyFilter1.php'],
            [new TranslationKeyFilter(), ['foobar'], [], []],
        ];
    }
}
