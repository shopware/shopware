<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[Group('store-api')]
class LandingPageLayoutDataMappingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testMappedTextServesTheLandingPageName(): void
    {
        $this->createLandingPage();
        $this->persistLayout();

        $this->browser->request('GET', '/store-api/content/landing-page/' . $this->ids->get('landing-page'));

        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('Campaign landing page', $body['elements'][0]['slots']['content'][0]['properties']['text']);
    }

    private function createLandingPage(): void
    {
        $this->repository('landing_page.repository')->create([[
            'id' => $this->ids->create('landing-page'),
            'name' => 'Campaign landing page',
            'url' => 'campaign',
            'active' => true,
            'salesChannels' => [['id' => $this->ids->get('sales-channel')]],
        ]], Context::createDefaultContext());
    }

    private function persistLayout(): void
    {
        $context = Context::createDefaultContext();

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->create('layout'),
            'name' => 'landing-page-data-mapping',
            'version' => '1.0.0',
            'rootSource' => 'landing_page',
            'layout' => [[
                'id' => $this->ids->create('root-grid'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => ['content' => [[
                    'id' => $this->ids->create('text'),
                    'component' => 'Sw:Content:Text',
                    'properties' => ['text' => 'Authored fallback'],
                    'acceptsContext' => [
                        'landing_page.name' => [
                            'type' => 'single',
                            'required' => false,
                            'propertyAlias' => 'text',
                            'scope' => 'root',
                        ],
                    ],
                ]]],
            ]],
        ]], $context);

        $this->repository('landing_page_content_layout.repository')->create([[
            'id' => $this->ids->create('assignment'),
            'landingPageId' => $this->ids->get('landing-page'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
