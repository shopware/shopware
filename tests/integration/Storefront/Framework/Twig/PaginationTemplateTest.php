<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class PaginationTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testPaginationWithExcessivePageNumberDoesNotExhaustMemory(): void
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $output = $twig->render('@Storefront/storefront/component/pagination.html.twig', [
            'currentPage' => 100000000,
            'totalPages' => 2,
        ]);

        static::assertStringContainsString('class="pagination"', $output);
        static::assertStringContainsString('data-page="2"', $output);
        static::assertStringNotContainsString('data-page="100000000"', $output);
    }

    public function testPaginationWithNegativePageNumberDefaultsToOne(): void
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $output = $twig->render('@Storefront/storefront/component/pagination.html.twig', [
            'currentPage' => -10,
            'totalPages' => 3,
        ]);

        static::assertStringContainsString('class="pagination"', $output);
        static::assertStringContainsString('data-page="1"', $output);
    }
}
