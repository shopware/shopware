<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ApiControllerAggregateTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAggregate(): void
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => 'SW-API-14999',
            'stock' => 1,
            'name' => 'asdf',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/product', $product);

        $data = [
            'aggregations' => [
                [
                    'name' => 'total',
                    'field' => 'id',
                    'type' => 'count',
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/aggregate/product', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // data is empty as we only do aggregations
        static::assertSame([], $content['data']);
        static::assertArrayHasKey('aggregations', $content);
        static::assertSame(1, $content['aggregations']['total']['count']);
    }

    #[DataProvider('responseTypeProvider')]
    public function testRestrictedUserCannotExposeProtectedFieldsThroughEntityAggregation(string $accept): void
    {
        $browser = $this->getBrowser(permissions: ['user:read']);
        $context = Context::createDefaultContext();
        $userRepository = static::getContainer()->get('user.repository');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('username', $this->apiUsernames[0]));
        $user = $userRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $userRepository->update([
            ['id' => $user->getId(), 'storeToken' => 'protected-store-token'],
        ], $context);

        $browser->jsonRequest('POST', '/api/aggregate/user', [
            'ids' => [$user->getId()],
            'aggregations' => [
                ['name' => 'users', 'type' => 'entity', 'definition' => 'user', 'field' => 'id'],
            ],
            'includes' => [
                'user' => ['username.x', 'password.x', 'storeToken.x'],
            ],
        ], server: ['HTTP_ACCEPT' => $accept]);

        $response = $browser->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertCount(1, $content['aggregations']['users']['entities']);
        $entity = $content['aggregations']['users']['entities'][0];
        static::assertSame('user', $entity['apiAlias']);
        static::assertArrayNotHasKey('password', $entity);
        static::assertArrayNotHasKey('storeToken', $entity);
    }

    public static function responseTypeProvider(): iterable
    {
        yield 'JSON aggregation response' => ['application/json'];
        yield 'JSON API aggregation response' => ['application/vnd.api+json'];
    }
}
