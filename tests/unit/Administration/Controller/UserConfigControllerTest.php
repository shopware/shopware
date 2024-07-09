<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\UserConfigController;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Controller\Exception\ExpectedUserHttpException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('system-settings')]
#[CoversClass(UserConfigController::class)]
class UserConfigControllerTest extends TestCase
{
    private MockObject&Connection $connection;

    private MockObject&EntityRepository $userConfigRepository;

    private Context $context;

    protected function setup(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->userConfigRepository = $this->createMock(EntityRepository::class);
        $this->context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
    }

    public function testGetConfigMeReturnsEmptyData(): void
    {
        $controller = new UserConfigController($this->userConfigRepository, $this->connection);

        $response = $controller->getConfigMe($this->context, new Request());

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":[]}', $response->getContent());
    }

    public function testGetConfigMeThrowsExpectedUserHttpExceptionWhenNoUserId(): void
    {
        $this->expectExceptionObject(new ExpectedUserHttpException());

        $controller = new UserConfigController($this->userConfigRepository, $this->connection);

        $response = $controller->getConfigMe(Context::createDefaultContext(new AdminApiSource(null)), new Request());

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":[]}', $response->getContent());
    }

    public function testGetConfigMeThrowsInvalidContextSourceExceptionWhenWrongSource(): void
    {
        $this->expectExceptionObject(new InvalidContextSourceException(AdminApiSource::class, SystemSource::class));

        $controller = new UserConfigController($this->userConfigRepository, $this->connection);

        $response = $controller->getConfigMe(Context::createDefaultContext(), new Request());

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":[]}', $response->getContent());
    }

    public function testGetConfigMeReturnsDataWithKeys(): void
    {
        $userConfigEntity = new UserConfigEntity();
        $userConfigEntity->setUniqueIdentifier(Uuid::randomHex());
        $userConfigEntity->setKey('testKey');
        $collection = new UserConfigCollection([$userConfigEntity]);

        $this->userConfigRepository->expects(static::once())->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'userConfig',
                    1,
                    $collection,
                    null,
                    new Criteria(),
                    $this->context
                )
            );

        $controller = new UserConfigController($this->userConfigRepository, $this->connection);

        $response = $controller->getConfigMe($this->context, new Request(['keys' => ['testKey']]));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":{"testKey": null}}', $response->getContent());
    }

    public function testUpdateConfigMeReturnsEmptyDataWhenNoPostUpdateConfigs(): void
    {
        $controller = new UserConfigController($this->userConfigRepository, $this->connection);

        $response = $controller->updateConfigMe($this->context, new Request([], []));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{}', $response->getContent());
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUpdateConfigPerformsMassUpsertEmptyWhenPostUpdateConfigs(): void
    {
        $userConfigEntity = new UserConfigEntity();
        $userConfigEntity->setId(Uuid::randomHex());
        $userConfigEntity->setUniqueIdentifier(Uuid::randomHex());
        $userConfigEntity->setKey('testKey');
        $collection = new UserConfigCollection([$userConfigEntity]);

        $this->userConfigRepository->expects(static::once())->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'userConfig',
                    1,
                    $collection,
                    null,
                    new Criteria(),
                    $this->context
                )
            );

        $controller = new UserConfigController($this->userConfigRepository, $this->connection);

        $response = $controller->updateConfigMe($this->context, new Request([], ['product' => true, 'testKey' => true]));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{}', $response->getContent());
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
