<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Salutation\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group store-api
 */
class SalutationRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testSalutation(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/salutation',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('salutationKey', $response['elements'][0]);
        static::assertArrayHasKey('displayName', $response['elements'][0]);
        static::assertArrayHasKey('letterName', $response['elements'][0]);
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/salutation',
                [
                    'includes' => [
                        'salutation' => ['id'],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayNotHasKey('displayName', $response['elements'][0]);
        static::assertArrayNotHasKey('letterName', $response['elements'][0]);
    }

    public function testLimit(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/salutation',
                [
                    'limit' => 1,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('displayName', $response['elements'][0]);
        static::assertArrayHasKey('letterName', $response['elements'][0]);
    }

    public function testDefaultSalutationIsExcluded(): void
    {
        $repository = static::createMock(SalesChannelRepositoryInterface::class);
        $repository->expects(static::exactly(1))
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria): bool {
                    if (\count($criteria->getFilters()) < 1) {
                        return false;
                    }

                    $filter = $criteria->getFilters()[0];

                    if (!($filter instanceof NotFilter)) {
                        return false;
                    }

                    if ($filter->getOperator() !== $filter::CONNECTION_OR) {
                        return false;
                    }

                    if (\count($filter->getQueries()) < 1) {
                        return false;
                    }

                    $query = $filter->getQueries()[0];

                    if (!($query instanceof EqualsFilter)) {
                        return false;
                    }

                    if ($query->getField() !== 'id') {
                        return false;
                    }

                    if ($query->getValue() !== Defaults::SALUTATION) {
                        return false;
                    }

                    return true;
                }),
                static::anything()
            );

        $route = new SalutationRoute($repository);

        $route->load(
            static::createStub(Request::class),
            static::createStub(SalesChannelContext::class),
            new Criteria()
        );
    }
}
