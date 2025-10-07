<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
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

    public function testActiveRouteParamsAreProperlyEscaped(): void
    {
        $response = $this->request('GET', '/', []);

        static::assertSame(200, $response->getStatusCode());
        // note that the json is properly escaped by validating the first character being unicode escaped and not a plain JSON string
        static::assertStringContainsString("window.activeRouteParameters = '\u00", $response->getContent() ?: '');
    }
}
