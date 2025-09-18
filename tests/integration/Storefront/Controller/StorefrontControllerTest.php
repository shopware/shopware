<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
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
    use SessionTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private string $testCategoryId;

    protected function setUp(): void
    {
        $this->testCategoryId = $this->createTestCategory();
    }

    protected function tearDown(): void
    {
        // Cleanup is automatically handled by DatabaseTransactionBehaviour
        // but we can be explicit about it for clarity
        $this->cleanupTestCategory();
    }

    public function testActiveRouteParamsAreProperlyEscaped(): void
    {
        $response = $this->request('GET', "navigation/{$this->testCategoryId}", []);

        // If it's a redirect (301/302), follow it to get the final page
        if (\in_array($response->getStatusCode(), [301, 302], true)) {
            $location = $response->headers->get('Location');
            static::assertNotNull($location, 'Redirect response should have Location header');

            // Extract the path from the location URL
            $path = parse_url($location, \PHP_URL_PATH);
            static::assertNotNull($path, 'Location header should have a valid path');

            // Follow the redirect
            $response = $this->request('GET', ltrim($path, '/'), []);
        }

        static::assertSame(200, $response->getStatusCode());

        $content = $response->getContent() ?: '';

        // Check that route parameters are present and properly JSON escaped
        // When route parameters contain the navigationId (UUID with special chars),
        // they should be properly escaped for JavaScript
        static::assertStringContainsString('window.activeRouteParameters = ', $content);

        // Verify that we have actual route parameters (not null)
        static::assertStringNotContainsString('window.activeRouteParameters = \'null\'', $content);

        // Check that the JSON is properly escaped by looking for escaped quotes or unicode
        $this->assertValidJsonEscaping($content);
    }

    private function createTestCategory(): string
    {
        // Generate a unique ID for each test run to avoid conflicts
        $categoryId = Uuid::randomHex();

        // Get the root category (sales channel navigation root)
        $connection = static::getContainer()->get('Doctrine\DBAL\Connection');
        $rootId = $connection->fetchOne('SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel LIMIT 1');

        // Create category with special characters to test escaping
        $category = [
            'id' => $categoryId,
            'name' => 'Test Category "with quotes" & <special> chars \u00e9',
            'type' => 'page',
            'parentId' => $rootId,
            'active' => true,
            'visible' => true,
        ];

        static::getContainer()->get('category.repository')->create(
            [$category],
            Context::createDefaultContext()
        );

        return $categoryId;
    }

    private function cleanupTestCategory(): void
    {
        // Note: This cleanup is redundant when using DatabaseTransactionBehaviour
        // as the transaction is automatically rolled back, but keeping it for explicitness
        try {
            static::getContainer()->get('category.repository')->delete(
                [['id' => $this->testCategoryId]],
                Context::createDefaultContext()
            );
        } catch (\Throwable $e) {
            // Ignore cleanup errors as the transaction rollback will handle it
        }
    }

    private function assertValidJsonEscaping(string $content): void
    {
        // Extract the activeRouteParameters value
        if (preg_match('/window\.activeRouteParameters = \'([^\']*)\';/', $content, $matches)) {
            $escapedJson = $matches[1];

            // The escaped content should not contain unescaped quotes or other dangerous characters
            static::assertStringNotContainsString('"', $escapedJson, 'Quotes should be escaped');
            static::assertStringNotContainsString('<', $escapedJson, 'Less-than should be escaped');
            static::assertStringNotContainsString('>', $escapedJson, 'Greater-than should be escaped');
            static::assertStringNotContainsString('&', $escapedJson, 'Ampersand should be escaped');

            // Should contain properly escaped content
            static::assertMatchesRegularExpression('/\\\\u[0-9a-fA-F]{4}/', $escapedJson, 'Should contain unicode-escaped characters');
        } else {
            static::fail('Could not find activeRouteParameters in response content');
        }
    }
}
