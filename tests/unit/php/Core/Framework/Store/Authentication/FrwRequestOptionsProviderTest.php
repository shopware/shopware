<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider
 */
class FrwRequestOptionsProviderTest extends TestCase
{
    public function testGetAuthenticationHeaderReturnsFrwToken(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue([
            'frwUserToken' => 'frw-user-token',
        ]);

        $result = new EntitySearchResult(
            UserConfigDefinition::ENTITY_NAME,
            1,
            new UserConfigCollection([$userConfig]),
            null,
            new Criteria(),
            $context
        );

        $userConfigRepositoryMock = static::createMock(EntityRepositoryInterface::class);
        $userConfigRepositoryMock->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::assertEquals([
            'X-Shopware-Token' => 'frw-user-token',
        ], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderReturnsEmptyArrayIfFrwTokenIsNull(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue([]);

        $result = new EntitySearchResult(
            UserConfigDefinition::ENTITY_NAME,
            1,
            new UserConfigCollection([$userConfig]),
            null,
            new Criteria(),
            $context
        );

        $userConfigRepositoryMock = static::createMock(EntityRepositoryInterface::class);
        $userConfigRepositoryMock->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::assertEquals([], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderReturnsEmptyArrayIfUserConfigCanNotBeFound(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $result = new EntitySearchResult(
            UserConfigDefinition::ENTITY_NAME,
            1,
            new UserConfigCollection(),
            null,
            new Criteria(),
            $context
        );

        $userConfigRepositoryMock = static::createMock(EntityRepositoryInterface::class);
        $userConfigRepositoryMock->expects(static::once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::assertEquals([], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderThrowsIfContextIsNoAdminApiSource(): void
    {
        $context = Context::createDefaultContext();

        $userConfigRepositoryMock = static::createMock(EntityRepositoryInterface::class);
        $userConfigRepositoryMock->expects(static::never())
            ->method('search');

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::expectException(InvalidContextSourceException::class);
        $frwRequestOptionsProvider->getAuthenticationHeader($context);
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testGetDefaultQueryParametersTakesContextAndLanguageBefore65(): void
    {
        $context = Context::createDefaultContext();
        $language = 'en-GB';

        $userConfigRepositoryMock = static::createMock(EntityRepositoryInterface::class);

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);
        $innerOptionsProvider->expects(static::once())
            ->method('getDefaultQueryParameters')
            ->with($context, $language)
            ->willReturn([
                'queries' => 'some-queries',
            ]);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        $queries = $frwRequestOptionsProvider->getDefaultQueryParameters($context, $language);

        static::assertEquals([
            'queries' => 'some-queries',
        ], $queries);
    }

    public function testGetDefaultQueryParametersDelegatesToInnerProvider(): void
    {
        $context = Context::createDefaultContext();

        $userConfigRepositoryMock = static::createMock(EntityRepositoryInterface::class);

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);
        $innerOptionsProvider->expects(static::once())
            ->method('getDefaultQueryParameters')
            ->with($context)
            ->willReturn([
                'queries' => 'some-queries',
            ]);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        $queries = $frwRequestOptionsProvider->getDefaultQueryParameters($context);

        static::assertEquals([
            'queries' => 'some-queries',
        ], $queries);
    }
}
