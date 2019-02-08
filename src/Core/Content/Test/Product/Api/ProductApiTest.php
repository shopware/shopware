<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
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
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $id,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 100, 'net' => 100],
                ],
            ],
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $context = Context::createDefaultContext();
        $products = $this->repository->search(new Criteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(1, $product->getPriceRules());

        /** @var ProductPriceRuleEntity $price */
        $price = $product->getPriceRules()->first();
        static::assertEquals($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'priceRules' => [
                //update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => ['gross' => 5000, 'net' => 4000],
                ],

                //add new graduation to existing rule
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 10, 'net' => 50],
                ],
            ],
        ];

        $this->getClient()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $products = $this->repository->search(new Criteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(2, $product->getPriceRules());

        /** @var ProductPriceRuleEntity $price */
        $price = $product->getPriceRules()->get($id);
        static::assertEquals($ruleA, $price->getRuleId());
        static::assertEquals(new Price(4000, 5000, false), $price->getPrice());

        static::assertEquals(1, $price->getQuantityStart());
        static::assertEquals(20, $price->getQuantityEnd());

        $id3 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'priceRules' => [
                [
                    'id' => $id3,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 50, 'net' => 50],
                ],
            ],
        ];

        $this->getClient()->request('PATCH', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $products = $this->repository->search(new Criteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(3, $product->getPriceRules());

        /** @var ProductPriceRuleEntity $price */
        $price = $product->getPriceRules()->get($id3);
        static::assertEquals($ruleB, $price->getRuleId());
        static::assertEquals(new Price(50, 50, false), $price->getPrice());

        static::assertEquals(1, $price->getQuantityStart());
        static::assertNull($price->getQuantityEnd());
    }

    public function testSpecialCharacterInDescriptionTest(): void
    {
        $id = Uuid::uuid4()->getHex();

        $description = '<p>Dies ist ein Typoblindtext. An ihm kann man sehen, ob alle Buchstaben da sind und wie sie aussehen. Manchmal benutzt man Worte wie Hamburgefonts, Rafgenduks oder Handgloves, um Schriften zu testen. Manchmal Sätze, die alle Buchstaben des Alphabets enthalten - man nennt diese Sätze »Pangrams«. Sehr bekannt ist dieser: The quick brown fox jumps over the lazy old dog. Oft werden in Typoblindtexte auch fremdsprachige Satzteile eingebaut (AVAIL® and Wefox™ are testing aussi la Kerning), um die Wirkung in anderen Sprachen zu testen. In Lateinisch sieht zum Beispiel fast jede Schrift gut aus. Quod erat demonstrandum. Seit 1975 fehlen in den meisten Testtexten die Zahlen, weswegen nach TypoGb. 204 § ab dem Jahr 2034 Zahlen in 86 der Texte zur Pflicht werden. Nichteinhaltung wird mit bis zu 245 € oder 368 $ bestraft. Genauso wichtig in sind mittlerweile auch Âçcèñtë, die in neueren Schriften aber fast immer enthalten sind. Ein wichtiges aber schwierig zu integrierendes Feld sind OpenType-Funktionalitäten. Je nach Software und Voreinstellungen können eingebaute Kapitälchen, Kerning oder Ligaturen (sehr pfiffig) nicht richtig dargestellt werden.Dies ist ein Typoblindtext. An ihm kann man sehen, ob alle Buchstaben da sind und wie sie aussehen. Manchmal benutzt man Worte wie Hamburgefonts, Rafgenduks</p>';

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'descriptionLong' => $description,
        ];

        $this->getClient()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/product', $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/product/' . $id, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $product = json_decode($response->getContent(), true);

        static::assertNotEmpty($product);
        static::assertArrayHasKey('data', $product);
        static::assertSame($description, $product['data']['descriptionLong']);
    }
}
