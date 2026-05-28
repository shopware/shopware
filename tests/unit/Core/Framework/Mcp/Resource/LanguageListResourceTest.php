<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Resource\LanguageListResource;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LanguageListResource::class)]
class LanguageListResourceTest extends TestCase
{
    public function testReturnsFormattedLanguages(): void
    {
        $id = Uuid::randomHex();
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId($id);
        $language->setName('English');
        $language->setLocale($locale);

        $collection = new LanguageCollection([$language]);
        $context = Context::createDefaultContext();

        $searchResult = new EntitySearchResult(
            'language',
            1,
            $collection,
            null,
            new Criteria(),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $resource = new LanguageListResource($repository);
        $result = ($resource)();

        static::assertSame('shopware://languages', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $data = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data);
        static::assertSame($id, $data[0]['id']);
        static::assertSame('English', $data[0]['name']);
        static::assertSame('en-GB', $data[0]['localeCode']);
    }
}
