<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\CustomEntity\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\System\CustomEntity\CustomEntityTest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CustomEntityApiControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * All other cases are covered in @see CustomEntityTest
     * as they need a complex and time-consuming setup, because they need DB schema updates
     */
    public function testSearchOnNonExistingCustomEntitiesResultsIn404(): void
    {
        $browser = $this->getBrowser();
        $browser->request('POST', '/api/search/custom-entity-non-existing');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }
}
