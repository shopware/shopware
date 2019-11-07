<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Twig\Extension\SwSanitizeTwigFilter;

class SwSanitizeTwigFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private $unfilteredString = '<div style="background-color:#0E75FB;">test</div>';

    public function testForbiddenElementAllowedAttribute(): void
    {
        $filter = new SwSanitizeTwigFilter();

        $filteredString = $filter->sanitize($this->unfilteredString, ['h1' => ['style']], true);
        static::assertSame($filteredString, 'test');
    }

    public function testAllowedElementForbiddenAttribute(): void
    {
        $filter = new SwSanitizeTwigFilter();

        $filteredString = $filter->sanitize($this->unfilteredString, ['div' => []], true);
        static::assertSame($filteredString, '<div>test</div>');
    }

    public function testForbiddenElementForbiddenAttribute(): void
    {
        $filter = new SwSanitizeTwigFilter();

        $filteredString = $filter->sanitize($this->unfilteredString, '', true);
        static::assertSame($filteredString, 'test');
    }

    public function testAllowedElementAllowedAttribute(): void
    {
        $filter = new SwSanitizeTwigFilter();

        $filteredString = $filter->sanitize($this->unfilteredString, ['div' => ['style']], true);
        static::assertSame($filteredString, $this->unfilteredString);
    }
}
