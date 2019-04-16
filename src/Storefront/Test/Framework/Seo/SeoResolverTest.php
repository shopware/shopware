<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoResolver;
use Shopware\Storefront\Framework\Seo\SeoResolverInterface;

class SeoResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var SeoResolverInterface
     */
    private $seoResolver;

    public function setUp(): void
    {
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->seoResolver = $this->getContainer()->get(SeoResolver::class);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM `sales_channel`');
    }

    public function testResolveEmpty(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), '');
        static::assertEquals(['pathInfo' => '/', 'isCanonical' => false], $resolved);

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), '/');
        static::assertEquals(['pathInfo' => '/', 'isCanonical' => false], $resolved);

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), '//');
        static::assertEquals(['pathInfo' => '/', 'isCanonical' => false], $resolved);
    }

    public function testResolveSeoPathPassthrough(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), '/foo/bar');
        static::assertEquals(['pathInfo' => '/foo/bar/', 'isCanonical' => false], $resolved);

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), 'foo/bar');
        static::assertEquals(['pathInfo' => '/foo/bar/', 'isCanonical' => false], $resolved);
    }

    public function testResolveSeoPath(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->createSalesChannel(Uuid::randomHex(), 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannel->getId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/invalid/product',
                'seoPathInfo' => 'awesome-product',
                'isValid' => false,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $salesChannel->getId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product',
                'isValid' => true,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $salesChannel->getId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-v2',
                'isValid' => true,
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        // pathInfo
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), 'detail/1234');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), '/detail/1234');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);

        // old canonical
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), 'awesome-product');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), '/awesome-product');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);

        // canonical
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), 'awesome-product-v2');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(1, $resolved['isCanonical']);
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannel->getId(), '/awesome-product-v2');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(1, $resolved['isCanonical']);
    }

    public function testResolveCanonMultiLang(): void
    {
        $salesChannelDe = $this->createSalesChannel(
            Uuid::randomHex(),
            'de',
            Defaults::LANGUAGE_SYSTEM_DE,
            [Defaults::LANGUAGE_SYSTEM, Defaults::LANGUAGE_SYSTEM_DE]
        );

        $deId = Uuid::randomHex();
        $enId = Uuid::randomHex();

        $this->seoUrlRepository->create([
            [
                'id' => $deId,
                'salesChannelId' => $salesChannelDe->getId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM_DE,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-de',
                'isValid' => true,
                'isCanonical' => true,
            ],
            [
                'id' => $enId,
                'salesChannelId' => $salesChannelDe->getId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-en',
                'isValid' => true,
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $actual = $this->seoResolver->resolveSeoPath(Defaults::LANGUAGE_SYSTEM_DE, $salesChannelDe->getId(), 'awesome-product-de');
        static::assertEquals($deId, Uuid::fromBytesToHex($actual['id']));

        $actual = $this->seoResolver->resolveSeoPath(Defaults::LANGUAGE_SYSTEM, $salesChannelDe->getId(), 'awesome-product-en');
        static::assertEquals($enId, Uuid::fromBytesToHex($actual['id']));
    }

    public function testResolveSamePathForDifferentSalesChannels(): void
    {
        $other = $this->createSalesChannel(
            Uuid::randomHex(),
            'de',
            Defaults::LANGUAGE_SYSTEM_DE,
            [Defaults::LANGUAGE_SYSTEM, Defaults::LANGUAGE_SYSTEM_DE]
        );

        $defaultId = Uuid::randomHex();
        $otherId = Uuid::randomHex();

        $this->seoUrlRepository->create([
            [
                'id' => $defaultId,
                'salesChannelId' => null, // default
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/default',
                'seoPathInfo' => 'awesome-product',
                'isValid' => true,
                'isCanonical' => true,
            ],
            [
                'id' => $otherId,
                'salesChannelId' => $other->getId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/other',
                'seoPathInfo' => 'awesome-product',
                'isValid' => true,
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $unknownSalesChannelId = Uuid::randomHex();
        // returns default for unknown sales channels
        $actual = $this->seoResolver->resolveSeoPath(Defaults::LANGUAGE_SYSTEM, $unknownSalesChannelId, 'awesome-product');
        static::assertEquals('/detail/default/', $actual['pathInfo']);
        static::assertTrue((bool) $actual['isCanonical']);

        $actual = $this->seoResolver->resolveSeoPath(Defaults::LANGUAGE_SYSTEM, $other->getId(), 'awesome-product');
        static::assertEquals('/detail/other/', $actual['pathInfo']);
        static::assertTrue((bool) $actual['isCanonical']);
    }

    private function createSalesChannel(string $id, string $name, string $defaultLanguageId = Defaults::LANGUAGE_SYSTEM, array $languageIds = []): SalesChannelEntity
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('sales_channel.repository');
        $languageIds[] = $defaultLanguageId;
        $languageIds = array_unique($languageIds);

        $languages = [];
        foreach ($languageIds as $langId) {
            $languages[] = ['id' => $langId];
        }

        $repo->upsert([[
            'id' => $id,
            'name' => $name,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => Uuid::randomHex(),
            'secretAccessKey' => 'foobar',
            'languageId' => $defaultLanguageId,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $languages,
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ]], Context::createDefaultContext());

        return $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
    }
}
