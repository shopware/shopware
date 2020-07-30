<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\CustomField\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class CustomFieldSetActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetAvailableRelations(): void
    {
        $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/_action/attribute-set/relations');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('application/json', $response->headers->get('Content-Type'));

        $availableRelations = \json_decode($response->getContent(), true);
        static::assertNotEmpty($availableRelations);

        static::assertContains('product', $availableRelations);
        static::assertNotContains('product_translation', $availableRelations);

        static::assertContains('product_manufacturer', $availableRelations);
        static::assertNotContains('product-manufacturer', $availableRelations);
    }
}
