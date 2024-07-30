<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminTagController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(AdminTagController::class)]
class AdminTagControllerTest extends TestCase
{
    public function testFilterIds(): void
    {
        $filterTagIdsService = $this->createMock(FilterTagIdsService::class);
        $controller = new AdminTagController($filterTagIdsService);

        $response = $controller->filterIds(new Request(), new Criteria(), Context::createDefaultContext());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"total":0,"ids":[]}', $response->getContent());
    }
}
