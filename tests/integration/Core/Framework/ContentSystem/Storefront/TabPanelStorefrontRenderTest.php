<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Storefront;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the connection `Sw:Tabs:Panel` derives between a navigation item and its tab pane.
 *
 * Neither side of that connection is authored: the panel reads the content element id off each
 * `Sw:Tabs:Tab` in its slot to build the trigger, and the tab reads the same id off its own
 * `data-element-id` to build the pane. The assertions therefore address nodes by the element id the
 * layout was persisted with, so a panel that emits internally consistent but element-unrelated ids is
 * a failure rather than a passing round trip.
 *
 * The open tab is pinned on both sides at once, because the panel decides the navigation state from
 * the tabs' `active` property while each tab decides its own pane state from the same property, and
 * those two decisions are what can drift apart.
 *
 * @internal
 */
#[Package('framework')]
class TabPanelStorefrontRenderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->createCategory();
    }

    #[TestDox('addresses the tab pane of the same tab element from every navigation item')]
    public function testNavigationItemsAddressTheTabPaneOfTheirOwnTabElement(): void
    {
        $this->persistTabPanelLayout(openTab: 'reviews');

        $xpath = $this->renderedLayout();

        foreach (['description', 'reviews'] as $tab) {
            $elementId = $this->ids->get($tab);

            $trigger = $this->singleNode($xpath, \sprintf('//button[@id="sw-tab-%s"]', $elementId));
            $pane = $this->singleNode($xpath, \sprintf('//div[@id="sw-tab-pane-%s"]', $elementId));

            static::assertSame('#sw-tab-pane-' . $elementId, $trigger->getAttribute('data-bs-target'));
            static::assertSame('sw-tab-pane-' . $elementId, $trigger->getAttribute('aria-controls'));
            static::assertSame('tab', $trigger->getAttribute('data-bs-toggle'));

            static::assertSame('sw-tab-' . $elementId, $pane->getAttribute('aria-labelledby'));
            // The tab derives its pane ids from this attribute, so the pane really belongs to the persisted element.
            static::assertSame($elementId, $pane->getAttribute('data-element-id'));
        }
    }

    #[TestDox('renders the title of each tab as the label of its navigation item')]
    public function testTabTitlesBecomeTheNavigationLabels(): void
    {
        $this->persistTabPanelLayout(openTab: 'reviews');

        $xpath = $this->renderedLayout();

        static::assertSame(
            'Description',
            trim($this->singleNode($xpath, \sprintf('//button[@id="sw-tab-%s"]', $this->ids->get('description')))->textContent)
        );
        static::assertSame(
            'Reviews',
            trim($this->singleNode($xpath, \sprintf('//button[@id="sw-tab-%s"]', $this->ids->get('reviews')))->textContent)
        );
    }

    #[TestDox('opens the tab flagged as open by default on both the navigation and the pane')]
    public function testTabFlaggedAsOpenByDefaultIsTheOpenOne(): void
    {
        $this->persistTabPanelLayout(openTab: 'reviews');

        $xpath = $this->renderedLayout();
        $reviews = $this->ids->get('reviews');

        static::assertSame('sw-tab-' . $reviews, $this->openNavigationItem($xpath)->getAttribute('id'));
        static::assertSame('sw-tab-pane-' . $reviews, $this->openTabPane($xpath)->getAttribute('id'));

        static::assertSame('true', $this->openNavigationItem($xpath)->getAttribute('aria-selected'));
        static::assertSame(
            'false',
            $this->singleNode($xpath, \sprintf('//button[@id="sw-tab-%s"]', $this->ids->get('description')))
                ->getAttribute('aria-selected')
        );
    }

    #[TestDox('opens the first tab when no tab is flagged as open by default')]
    public function testFirstTabOpensWhenNoTabIsFlaggedAsOpenByDefault(): void
    {
        $this->persistTabPanelLayout(openTab: null);

        $xpath = $this->renderedLayout();

        static::assertSame('sw-tab-' . $this->ids->get('description'), $this->openNavigationItem($xpath)->getAttribute('id'));
    }

    #[TestDox('renders the shipped tab panel preset as a panel with two paired tabs')]
    public function testShippedPresetRendersAWorkingPanel(): void
    {
        $this->persistLayout($this->presetPayload('Sw:TabPanel'));

        $xpath = $this->renderedLayout();

        $triggers = $xpath->query(
            '//ul[contains(concat(" ", normalize-space(@class), " "), " sw-tabs__nav ")]//button'
        );
        static::assertInstanceOf(\DOMNodeList::class, $triggers);
        static::assertCount(2, $triggers, 'The preset ships two tabs.');

        // The preset mints its own element ids, so the pairing is asserted through the trigger's target.
        foreach ($triggers as $trigger) {
            static::assertInstanceOf(\DOMElement::class, $trigger);

            $paneId = ltrim($trigger->getAttribute('data-bs-target'), '#');
            static::assertNotSame('', $paneId);

            $pane = $this->singleNode($xpath, \sprintf('//div[@id="%s"]', $paneId));
            static::assertSame($trigger->getAttribute('id'), $pane->getAttribute('aria-labelledby'));
        }

        static::assertSame('Description', trim($this->openNavigationItem($xpath)->textContent));
    }

    /**
     * The one navigation item carrying Bootstrap's `active` class. Asserting that it is unique is what makes a
     * panel that opens two tabs at once a failure.
     */
    private function openNavigationItem(\DOMXPath $xpath): \DOMElement
    {
        return $this->singleNode(
            $xpath,
            '//ul[contains(concat(" ", normalize-space(@class), " "), " sw-tabs__nav ")]'
            . '//button[contains(concat(" ", normalize-space(@class), " "), " active ")]'
        );
    }

    private function openTabPane(\DOMXPath $xpath): \DOMElement
    {
        return $this->singleNode(
            $xpath,
            '//div[contains(concat(" ", normalize-space(@class), " "), " sw-tab-pane ")]'
            . '[contains(concat(" ", normalize-space(@class), " "), " active ")]'
        );
    }

    private function singleNode(\DOMXPath $xpath, string $query): \DOMElement
    {
        $nodes = $xpath->query($query);

        static::assertInstanceOf(\DOMNodeList::class, $nodes);
        static::assertCount(1, $nodes, \sprintf('Exactly one node must match "%s".', $query));

        $node = $nodes->item(0);
        static::assertInstanceOf(\DOMElement::class, $node);

        return $node;
    }

    private function renderedLayout(): \DOMXPath
    {
        $response = $this->request('GET', 'content/category/' . $this->ids->get('category'), []);

        $html = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $html);

        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        static::assertTrue($loaded);

        return new \DOMXPath($document);
    }

    /**
     * @param 'description'|'reviews'|null $openTab the tab flagged `active`, or none at all
     */
    private function persistTabPanelLayout(?string $openTab): void
    {
        $this->persistLayout([[
            'id' => $this->ids->get('panel'),
            'component' => 'Sw:Tabs:Panel',
            'properties' => [],
            'slots' => [
                'tabs' => [
                    $this->tab('description', 'Description', $openTab === 'description'),
                    $this->tab('reviews', 'Reviews', $openTab === 'reviews'),
                ],
            ],
        ]]);
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function persistLayout(array $elements): void
    {
        $context = Context::createDefaultContext();

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->get('layout'),
            'name' => 'tab-panel-render',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => $elements,
        ]], $context);

        $this->repository('category_content_layout.repository')->create([[
            'id' => $this->ids->get('assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    /**
     * The compiled element payload of a shipped preset, element ids already minted by the preset loader.
     *
     * @return list<array<string, mixed>>
     */
    private function presetPayload(string $presetId): array
    {
        $registry = static::getContainer()->get(ContentSystemLayoutPresetRegistry::class);
        static::assertInstanceOf(AbstractContentSystemLayoutPresetRegistry::class, $registry);
        static::assertTrue($registry->has($presetId), \sprintf('The "%s" preset must be registered.', $presetId));

        return $registry->get($presetId)->payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function tab(string $key, string $title, bool $active): array
    {
        return [
            'id' => $this->ids->get($key),
            'component' => 'Sw:Tabs:Tab',
            'properties' => [
                'title' => $title,
                'active' => $active,
            ],
            'slots' => [
                'content' => [[
                    'id' => $this->ids->get($key . '-text'),
                    'component' => 'Sw:Content:Text',
                    'properties' => [
                        'text' => '<p>' . $title . ' content</p>',
                    ],
                ]],
            ],
        ];
    }

    private function createCategory(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Tab panel category',
            'active' => true,
        ]], Context::createDefaultContext());
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
