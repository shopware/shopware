<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @group slow
 */
class OpenApi3Test extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;

    public function apiVersionDataProvider(): array
    {
        return array_map(static function ($v) {
            return [$v];
        }, $this->getContainer()->getParameter('kernel.supported_api_versions'));
    }

    /**
     * @dataProvider apiVersionDataProvider
     */
    public function testRequestOpenApi3Json($v): void
    {
        $this->getBrowser()->request('GET', '/api/v' . $v . '/_info/openapi3.json');

        $response = $this->getBrowser()->getResponse();

        $content = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode(), print_r($content, true));
    }
}
