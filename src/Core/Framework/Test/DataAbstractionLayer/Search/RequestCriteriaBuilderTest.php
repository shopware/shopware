<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class RequestCriteriaBuilderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    protected function setUp()
    {
        parent::setUp();
        $this->requestCriteriaBuilder = $this->getContainer()->get(RequestCriteriaBuilder::class);
    }

    public function testAssociationsAddedToCriteria()
    {
        $body = [
            'limit' => 10,
            'page' => 1,
            'associations' => [
                'priceRules' => [
                    'limit' => 25,
                    'page' => 1,
                    'filter' => [
                        ['type' => 'equals', 'field' => 'quantityStart', 'value' => 1],
                    ],
                    'sort' => [
                        ['field' => 'quantityStart'],
                    ],
                    'associations' => [
                        'currency' => [
                            'filter' => [
                                ['type' => 'equals', 'field' => 'id', 'value' => 'test'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $request = new Request([], $body, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $criteria = new Criteria();
        $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            ProductDefinition::class,
            Context::createDefaultContext()
        );

        static::assertTrue($criteria->hasAssociation('product.priceRules'));
        $nested = $criteria->getAssociation('product.priceRules');

        static::assertInstanceOf(Criteria::class, $nested);
        static::assertCount(1, $nested->getFilters());
        static::assertCount(1, $nested->getSorting());

        static::assertTrue($nested->hasAssociation('product_price_rule.currency'));
        $nested = $nested->getAssociation('product_price_rule.currency');
        static::assertInstanceOf(Criteria::class, $nested);
        static::assertCount(1, $nested->getFilters());
    }
}
