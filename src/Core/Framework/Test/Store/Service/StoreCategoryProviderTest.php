<?php declare(strict_types=1);

namespace Swag\SaasRufus\Test\Core\Framework\Extension\Service;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\AbstractStoreCategoryProvider;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class StoreCategoryProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    public function testGetCategories(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

        /** @var AbstractStoreCategoryProvider $categoryProvider */
        $categoryProvider = $this->getContainer()->get(AbstractStoreCategoryProvider::class);

        $categoryResponse = \file_get_contents(__DIR__ . '/../_fixtures/categories-listing.json');

        // add extensions to compare structs later
        $categoryAsArray = array_map(function ($category) {
            $category['extensions'] = [];

            return $category;
        }, \json_decode($categoryResponse, true));

        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], $categoryResponse));

        $categories = $categoryProvider->getCategories(Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));

        static::assertEquals(\count($categoryAsArray), $categories->count());
        static::assertEquals($categoryAsArray, \json_decode(\json_encode($categories), true));
    }
}
