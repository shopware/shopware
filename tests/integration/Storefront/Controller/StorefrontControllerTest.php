<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * @internal
 */
class StorefrontControllerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private string $testCategoryId;

    protected function setUp(): void
    {
        $salesChannel = $this->createSalesChannel();
        $this->testCategoryId = $this->createTestCategory($salesChannel['navigationCategoryId']);
    }

    /**
     * Test that active route parameters are properly escaped for JavaScript
     *
     * This test ensures that when route parameters (like UUIDs with special characters)
     * are included in the storefront response as JavaScript variables, they are properly
     * escaped to prevent XSS vulnerabilities.
     */
    public function testActiveRouteParamsAreProperlyEscaped(): void
    {
        $response = $this->request('GET', "navigation/{$this->testCategoryId}", []);

        // Follow redirects to get the final rendered page
        if (\in_array($response->getStatusCode(), [301, 302], true)) {
            $location = $response->headers->get('Location');
            static::assertNotNull($location, 'Redirect response should have Location header');

            $path = parse_url($location, \PHP_URL_PATH);
            static::assertIsString($path, 'Location header should have a valid path');

            $response = $this->request('GET', ltrim($path, '/'), []);
        }

        static::assertSame(200, $response->getStatusCode());
        $content = $response->getContent() ?: '';

        // Verify route parameters are present and not null
        static::assertStringContainsString('window.activeRouteParameters = ', $content);
        static::assertStringNotContainsString('window.activeRouteParameters = \'null\'', $content);

        // Verify proper JavaScript escaping
        $this->assertValidJsonEscaping($content);
    }

    /**
     * Creates a test category with special characters for testing JavaScript escaping
     */
    private function createTestCategory(string $rootId): string
    {
        $categoryId = Uuid::randomHex();

        $category = [
            'id' => $categoryId,
            'name' => 'Test Category "with quotes" & <special> chars \u00e9',
            'type' => 'page',
            'parentId' => $rootId,
            'active' => true,
            'visible' => true,
        ];

        self::getContainer()->get('category.repository')->create(
            [$category],
            Context::createDefaultContext()
        );

        return $categoryId;
    }

    /**
     * Validates that JavaScript variables are properly escaped to prevent XSS
     */
    private function assertValidJsonEscaping(string $content): void
    {
        static::assertMatchesRegularExpression(
            '/window\.activeRouteParameters = \'([^\']*)\';/',
            $content,
            'Could not find activeRouteParameters in response content'
        );

        preg_match('/window\.activeRouteParameters = \'([^\']*)\';/', $content, $matches);
        $escapedJson = $matches[1];

        // Verify dangerous characters are properly escaped
        $dangerousChars = ['"' => 'Quotes', '<' => 'Less-than', '>' => 'Greater-than', '&' => 'Ampersand'];
        foreach ($dangerousChars as $char => $description) {
            static::assertStringNotContainsString($char, $escapedJson, "{$description} should be escaped");
        }

        // Verify unicode escaping is present
        static::assertMatchesRegularExpression(
            '/\\\\u[0-9a-fA-F]{4}/',
            $escapedJson,
            'Should contain unicode-escaped characters'
        );
    }
}
