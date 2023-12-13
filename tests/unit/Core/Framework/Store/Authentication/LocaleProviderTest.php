<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LocaleProvider::class)]
class LocaleProviderTest extends TestCase
{
    public function testGetLocaleFromContextReturnsEnGbInSystemSource(): void
    {
        $provider = new LocaleProvider(static::createMock(EntityRepository::class));

        static::assertEquals('en-GB', $provider->getLocaleFromContext(Context::createDefaultContext()));
    }

    public function testGetLocaleFromContextReturnsEnGbIfNoUserIsAssociated(): void
    {
        $provider = new LocaleProvider(static::createMock(EntityRepository::class));

        static::assertEquals(
            'en-GB',
            $provider->getLocaleFromContext(Context::createDefaultContext(
                new AdminApiSource(null, 'i-am-an-integration')
            ))
        );
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id', null));

        $userLocale = new LocaleEntity();
        $userLocale->setCode('user-locale');

        $user = new UserEntity();
        $user->setUniqueIdentifier('user-identifier');
        $user->setLocale($userLocale);

        $userRepository = static::createMock(EntityRepository::class);
        $userRepository->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([$user]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($userRepository);

        static::assertEquals('user-locale', $provider->getLocaleFromContext($context));
    }

    public function testGetLocaleFromContextThrowsIfAssociatedUserCanNotBeFound(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id', null));

        $userRepository = static::createMock(EntityRepository::class);
        $userRepository->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($userRepository);

        static::expectException(EntityNotFoundException::class);
        $provider->getLocaleFromContext($context);
    }
}
