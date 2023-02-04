<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\CustomField\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 *
 * @group slow
 */
class CustomFieldSetActionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetAvailableRelations(): void
    {
        $this->getBrowser()->request('GET', '/api/_action/attribute-set/relations');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('application/json', $response->headers->get('Content-Type'));

        $availableRelations = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotEmpty($availableRelations);

        static::assertContains('product', $availableRelations);
        static::assertNotContains('product_translation', $availableRelations);

        static::assertContains('product_manufacturer', $availableRelations);
        static::assertNotContains('product-manufacturer', $availableRelations);
    }
}
