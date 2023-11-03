<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testAllowSettingNullToTranslatableFields(): void
    {
        $id = Uuid::randomHex();

        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->request('POST', '/api/' . $entityName, [
            'id' => $id,
            'features' => ['test' => true],
            'name' => 'test',
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->setServerParameter('HTTP_sw-language-id', $this->getDeDeLanguageId());

        $client->request('PATCH', '/api/' . $entityName . '/' . $id, [
            'id' => $id,
            'name' => null,
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
