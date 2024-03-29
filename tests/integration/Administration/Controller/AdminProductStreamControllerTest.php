<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Group('slow')]
class AdminProductStreamControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private IdsCollection $ids;

    protected function setup(): void
    {
        $this->ids = new IdsCollection();
        $this->prepareTestData();
    }

    public function testPreview(): void
    {
        $data = [
            'page' => 1,
            'limit' => 25,
            'filter' => [
                [
                    'field' => null,
                    'type' => 'multi',
                    'operator' => 'OR',
                    'value' => null,
                    'parameters' => null,
                    'queries' => [
                        [
                            'field' => null,
                            'type' => 'multi',
                            'operator' => 'AND',
                            'value' => null,
                            'parameters' => null,
                            'queries' => [
                                [
                                    'field' => 'cheapestPrice',
                                    'type' => 'range',
                                    'operator' => null,
                                    'value' => null,
                                    'parameters' => [
                                        'lt' => '500',
                                    ],
                                    'queries' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request(
            'POST',
            '/api/_admin/product-stream-preview/' . TestDefaults::SALES_CHANNEL,
            [],
            [],
            [],
            json_encode($data) ?: ''
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(3, $content['elements']);
        $names = array_column($content['elements'], 'name');
        static::assertContains('v.1.1', $names);
        static::assertNotContains('v.1.2', $names);
        static::assertContains('v.2.1', $names);
        static::assertContains('v.2.2', $names);
    }

    public function testEqualsAllPreview(): void
    {
        $this->ids->create('rot');
        $this->ids->create('grün');

        $data = [
            'page' => 1,
            'limit' => 25,
            'filter' => [
                [
                    'field' => null,
                    'type' => 'multi',
                    'operator' => 'OR',
                    'value' => null,
                    'parameters' => null,
                    'queries' => [
                        [
                            'field' => null,
                            'type' => 'multi',
                            'operator' => 'AND',
                            'value' => null,
                            'parameters' => null,
                            'queries' => [
                                [
                                    'field' => 'properties.id',
                                    'type' => 'equalsAll',
                                    'operator' => null,
                                    'value' => $this->ids->get('grün') . '|' . $this->ids->get('rot'),
                                    'parameters' => null,
                                    'queries' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request(
            'POST',
            '/api/_admin/product-stream-preview/' . TestDefaults::SALES_CHANNEL,
            [],
            [],
            [],
            json_encode($data) ?: ''
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(3, $content['elements']);
        $names = array_column($content['elements'], 'name');
        static::assertContains('v.1.1', $names);
        static::assertContains('v.1.2', $names);
        static::assertNotContains('v.2.1', $names);
        static::assertNotContains('v.2.2', $names);
    }

    private function prepareTestData(): void
    {
        $products = [
            (new ProductBuilder($this->ids, 'p.1'))
                ->price(900)
                ->property('rot', 'Farbe')
                ->property('grün', 'Farbe')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->prices('rule-a', 220, 'default', null, 3, true)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.1.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.1.2'))
                        ->prices('rule-a', 210, 'default', null, 3, true)
                        ->prices('rule-b', 501, 'default', null, 3, true)
                        ->build()
                )
                ->build(),
            (new ProductBuilder($this->ids, 'p.2'))
                ->price(800)
                ->property('grün', 'Farbe')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->prices('rule-c', 200, 'default', null, 3, true)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.2.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.2.2'))
                        ->prices('rule-d', 501, 'default', null, 3, true)
                        ->prices('rule-e', 100, 'default', null, 3, true)
                        ->build()
                )
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $this->getContainer()->get('rule.repository')->upsert(
            [
                ['id' => $this->ids->get('rule-a'), 'priority' => 1],
                ['id' => $this->ids->get('rule-b'), 'priority' => 2],
                ['id' => $this->ids->get('rule-c'), 'priority' => 3],
                ['id' => $this->ids->get('rule-d'), 'priority' => 4],
                ['id' => $this->ids->get('rule-e'), 'priority' => 5],
            ],
            Context::createDefaultContext()
        );
    }
}
