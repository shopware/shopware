<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;

class AppCmsControllerTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_14408', $this);
    }

    public function testGetBlocks(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $url = '/api/app-system/cms/blocks';
        $this->getBrowser()->request('GET', $url);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());
        static::assertJsonStringEqualsJsonFile(__DIR__ . '/_fixtures/expectedCmsBlocks.json', $response->getContent());
    }
}
