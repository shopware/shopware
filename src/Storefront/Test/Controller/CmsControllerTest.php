<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Storefront\Page\Cms\CmsPageLoadedHook;

/**
 * @internal
 */
#[Package('content')]
class CmsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->createData();
    }

    public function testCmsPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/widgets/cms/' . $this->ids->get('page'), []);
        static::assertEquals(200, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CmsPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCmsPageLoadedHookScriptsAreExecutedForCategory(): void
    {
        $response = $this->request('GET', '/widgets/cms/navigation/' . $this->ids->get('category'), []);
        static::assertEquals(200, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CmsPageLoadedHook::HOOK_NAME, $traces);
    }

    private function createData(): void
    {
        $category = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'type' => 'landing_page',
            'cmsPage' => [
                'id' => $this->ids->create('page'),
                'name' => 'test page',
                'type' => 'landingpage',
                'sections' => [
                    [
                        'id' => $this->ids->create('section'),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'text',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'id' => $this->ids->create('slot1'),
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => [
                                            'content' => [
                                                'source' => 'static',
                                                'value' => 'initial',
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => $this->ids->create('slot2'),
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());
    }
}
