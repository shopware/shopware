<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class ProductApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
    }

    public function testModifyProductPriceMatrixOverApi(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $id,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $context = Context::createDefaultContext();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(1, $product->getPrices());

        /** @var ProductPriceEntity $price */
        $price = $product->getPrices()->first();
        static::assertEquals($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'prices' => [
                //update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5000, 'net' => 4000, 'linked' => false]],
                ],

                //add new graduation to existing rule
                [
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false]],
                ],
            ],
        ];

        $this->getBrowser()->request('PATCH', '/api/product/' . $id, [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(2, $product->getPrices());

        /** @var ProductPriceEntity $price */
        $price = $product->getPrices()->get($id);
        static::assertEquals($ruleA, $price->getRuleId());
        static::assertEquals(new Price(Defaults::CURRENCY, 4000, 5000, false), $price->getPrice()->get(Defaults::CURRENCY));

        static::assertEquals(1, $price->getQuantityStart());
        static::assertEquals(20, $price->getQuantityEnd());

        $id3 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'prices' => [
                [
                    'id' => $id3,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50, 'linked' => false]],
                ],
            ],
        ];

        $this->getBrowser()->request('PATCH', '/api/product/' . $id, [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(3, $product->getPrices());

        /** @var ProductPriceEntity $price */
        $price = $product->getPrices()->get($id3);
        static::assertEquals($ruleB, $price->getRuleId());
        static::assertEquals(new Price(Defaults::CURRENCY, 50, 50, false), $price->getPrice()->get(Defaults::CURRENCY));

        static::assertEquals(1, $price->getQuantityStart());
        static::assertNull($price->getQuantityEnd());
    }

    public function testSpecialCharacterInDescriptionTest(): void
    {
        $id = Uuid::randomHex();

        $description = '<p>Dies ist ein Typoblindtext. An ihm kann man sehen, ob alle Buchstaben da sind und wie sie aussehen. Manchmal benutzt man Worte wie Hamburgefonts, Rafgenduks oder Handgloves, um Schriften zu testen. Manchmal Sätze, die alle Buchstaben des Alphabets enthalten - man nennt diese Sätze »Pangrams«. Sehr bekannt ist dieser: The quick brown fox jumps over the lazy old dog. Oft werden in Typoblindtexte auch fremdsprachige Satzteile eingebaut (AVAIL® and Wefox™ are testing aussi la Kerning), um die Wirkung in anderen Sprachen zu testen. In Lateinisch sieht zum Beispiel fast jede Schrift gut aus. Quod erat demonstrandum. Seit 1975 fehlen in den meisten Testtexten die Zahlen, weswegen nach TypoGb. 204 § ab dem Jahr 2034 Zahlen in 86 der Texte zur Pflicht werden. Nichteinhaltung wird mit bis zu 245 € oder 368 $ bestraft. Genauso wichtig in sind mittlerweile auch Âçcèñtë, die in neueren Schriften aber fast immer enthalten sind. Ein wichtiges aber schwierig zu integrierendes Feld sind OpenType-Funktionalitäten. Je nach Software und Voreinstellungen können eingebaute Kapitälchen, Kerning oder Ligaturen (sehr pfiffig) nicht richtig dargestellt werden.Dies ist ein Typoblindtext. An ihm kann man sehen, ob alle Buchstaben da sind und wie sie aussehen. Manchmal benutzt man Worte wie Hamburgefonts, Rafgenduks</p>';

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'description' => $description,
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->getBrowser()->request('GET', '/api/product/' . $id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $product = json_decode($response->getContent(), true);

        static::assertNotEmpty($product);
        static::assertArrayHasKey('data', $product);
        static::assertSame($description, $product['data']['description']);
    }

    public function testIncludesWithJsonApi(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $data = [
            'id' => $ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $this->getBrowser()->request('POST', '/api/search/product', [], [], [], json_encode([
            'includes' => [
                'product' => ['id', 'name'],
            ],
        ]));

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $products = json_decode($response->getContent(), true);

        $product = $products['data'][0];

        static::assertArrayHasKey('attributes', $product);
        static::assertArrayHasKey('name', $product['attributes']);
        static::assertArrayNotHasKey('translated', $product['attributes']);
        static::assertArrayNotHasKey('manufacturerId', $product['attributes']);
        static::assertArrayNotHasKey('parentId', $product['attributes']);
        static::assertEmpty($product['relationships']);

        static::assertEmpty($products['included']);
    }

    public function testIncludesWithRelationships(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $data = [
            'id' => $ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $this->getBrowser()->request('POST', '/api/search/product', [], [], [], json_encode([
            'includes' => [
                'product' => ['id', 'name', 'tax'],
                'tax' => ['id', 'name'],
            ],
        ]));

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $products = json_decode($response->getContent(), true);

        $product = $products['data'][0];

        static::assertArrayHasKey('attributes', $product);
        static::assertArrayHasKey('name', $product['attributes']);
        static::assertArrayNotHasKey('translated', $product['attributes']);
        static::assertArrayNotHasKey('manufacturerId', $product['attributes']);
        static::assertArrayNotHasKey('parentId', $product['attributes']);
        static::assertCount(1, $product['relationships']);
        static::assertArrayHasKey('tax', $product['relationships']);

        static::assertCount(1, $products['included']);
        static::assertEquals('tax', $products['included'][0]['type']);
    }

    public function testInvalidCrossSelling(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->getBrowser()->request('POST', '/api/product', [], [], [], json_encode($data));
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $crossSellingPatch = [
            'crossSellings' => [
                [
                    'active' => true,
                ],
            ],
        ];

        $this->getBrowser()->request('PATCH', '/api/product/' . $id, [], [], [], json_encode($crossSellingPatch));

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
    }
}
