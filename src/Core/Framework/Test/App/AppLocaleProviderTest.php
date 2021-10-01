<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

class AppLocaleProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppLocaleProvider $localeProvider;

    private EntityRepositoryInterface $userRepository;

    public function setUp(): void
    {
        $this->localeProvider = $this->getContainer()->get(AppLocaleProvider::class);
        $this->userRepository = $this->getContainer()->get('user.repository');
    }

    public function testGetLocaleWithSystemSource(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(Context::createDefaultContext());

        static::assertEquals('en-GB', $locale);
    }

    public function testGetLocaleWithSalesChannelSource(): void
    {
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $locale = $this->localeProvider->getLocaleFromContext($context->getContext());

        static::assertEquals('en-GB', $locale);
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $userId = Uuid::randomHex();
        $userLocale = 'abc-de';

        $this->userRepository->create([[
            'id' => $userId,
            'username' => 'testUser',
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'first@last.de',
            'password' => 'shopware',
            'locale' => [
                'code' => $userLocale,
                'name' => 'testLocale',
                'territory' => 'somewhere',
            ],
        ]], Context::createDefaultContext());

        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $locale = $this->localeProvider->getLocaleFromContext($context);

        static::assertEquals($userLocale, $locale);
    }

    public function testGetLocaleFromContextReturnsEnglishForSystemContext(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(Context::createDefaultContext());

        static::assertEquals('en-GB', $locale);
    }

    public function testGetLocaleFromContextReturnsEnglishForIntegrations(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(
            Context::createDefaultContext(new AdminApiSource(null, Uuid::randomHex()))
        );

        static::assertEquals('en-GB', $locale);
    }
}
