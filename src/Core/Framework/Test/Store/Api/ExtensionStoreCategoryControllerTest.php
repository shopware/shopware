<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Api;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Api\ExtensionStoreCategoryController;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ExtensionStoreCategoryControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    /**
     * @var ExtensionStoreCategoryController
     */
    private $controller;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        parent::setUp();
        $this->controller = $this->getContainer()->get(ExtensionStoreCategoryController::class);
    }

    public function testCategories(): void
    {
        $categoryResponse = \file_get_contents(__DIR__ . '/../_fixtures/categories-listing.json');
        // add extensions to compare structs later
        $categoryAsArray = array_map(function ($category) {
            $category['extensions'] = [];

            return $category;
        }, \json_decode($categoryResponse, true));

        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], $categoryResponse));

        $response = $this->controller->getCategories(Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));

        static::assertSame(json_encode($categoryAsArray), $response->getContent());
    }
}
