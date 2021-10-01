<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;

class AppCmsControllerTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;

    public function testGetBlocks(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $this->getBrowser()->request('GET', '/api/app-system/cms/blocks');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());

        $expected = json_decode(file_get_contents(__DIR__ . '/_fixtures/expectedCmsBlocks.json'), true, 512, \JSON_THROW_ON_ERROR);
        $actual = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Remove template
        unset($expected['blocks'][1]['template'], $actual['blocks'][1]['template']);

        static::assertEquals(
            $expected,
            $actual
        );
    }
}
