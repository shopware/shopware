<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Snippet\Services\SnippetFlattener;
use Shopware\Core\Framework\Snippet\SnippetEntity;

class SnippetFlattenerTest extends TestCase
{
    public function testFlatten(): void
    {
        $flattener = $this->getFlattener();
        $arrayToFlatten = json_decode(file_get_contents(__DIR__ . '/../_fixtures/testLanguage.json'), true);

        $expectedResult = json_decode(file_get_contents(__DIR__ . '/../_fixtures/testLanguageFlatten.json'), true);
        $result = $flattener->flatten($arrayToFlatten);

        $this->assertArraySubset($expectedResult, $result);
    }

    public function testUnflatten(): void
    {
        $flattener = $this->getFlattener();
        $arrayToUnflatten = json_decode(file_get_contents(__DIR__ . '/../_fixtures/testLanguageFlatten.json'), true);

        $snippets = [];
        foreach ($arrayToUnflatten as $key => $item) {
            $snippet = new SnippetEntity();
            $snippet->setTranslationKey($key);
            $snippet->setValue($item);
            $snippets[] = $snippet;
        }

        $expectedResult = json_decode(file_get_contents(__DIR__ . '/../_fixtures/testLanguage.json'), true);
        $result = $flattener->unflatten($snippets);

        $this->assertArraySubset($expectedResult, $result);
    }

    private function getFlattener()
    {
        return new SnippetFlattener();
    }
}
