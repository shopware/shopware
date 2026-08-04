<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber;
use Shopware\Administration\Snippet\AppAdministrationSnippetCollection;
use Shopware\Administration\Snippet\AppAdministrationSnippetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\System\Service\SystemLanguageChangeEvent;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Locale\LocaleException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemLanguageChangedSubscriber::class)]
class SystemLanguageChangedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [SystemLanguageChangeEvent::class => 'onSystemLanguageChanged'],
            SystemLanguageChangedSubscriber::getSubscribedEvents()
        );
    }

    public function testDoesNotRunIfNoSnippetsExist(): void
    {
        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection()]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository = $this->createMock(EntityRepository::class),
            $snippetRepository
        );

        $localeRepository->expects($this->never())
            ->method('search');

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            'en-GB',
            'en-US',
        ));
    }

    public function testOnSystemLanguageChangedThrowsExceptionWhenNewLocaleDoesNotExist(): void
    {
        $localeRepository = static::createStub(EntityRepository::class);
        $snippetRepository = static::createStub(EntityRepository::class);

        $snippetCollection = new AppAdministrationSnippetCollection([
            (new AppAdministrationSnippetEntity())->assign([
                'id' => 'snippet-id',
                'appId' => 'app-id',
            ]),
        ]);

        $snippetSearchResult = static::createStub(EntitySearchResult::class);
        $snippetSearchResult->method('getEntities')->willReturn($snippetCollection);

        $snippetRepository->method('search')->willReturn($snippetSearchResult);

        $localeSearchResult = static::createStub(EntitySearchResult::class);
        $localeSearchResult->method('first')->willReturn(null);

        $localeRepository->method('search')->willReturn($localeSearchResult);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $this->expectExceptionObject(LocaleException::localeDoesNotExists('fr-DE'));

        $subscriber->onSystemLanguageChanged(
            new SystemLanguageChangeEvent(
                'previous-language-id',
                'fr-DE',
                'de-DE'
            )
        );
    }

    public function testDoesNotUpdateSnippetsIfSystemLanguageIsChangedFromEnGbToDeDe(): void
    {
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale('de-DE')]),
        ]);

        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $this->createSnippet('app-one-id', $previousLocale->getId()),
            $this->createSnippet('app-one-id', 'other-locale-id'),
            $this->createSnippet('app-two-id', $previousLocale->getId()),
            $this->createSnippet('app-two-id', 'other-locale-id'),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        static::assertEmpty($snippetRepository->creates);
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsForPreviousLocaleWithPreviousLocaleId(string $locale): void
    {
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale($locale)]),
        ]);

        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $snippetOneToUpdate = $this->createSnippet('app-one-id', $newLocale->getId()),
            $this->createSnippet('app-one-id', 'other-locale-id'),
            $snippetTwoToUpdate = $this->createSnippet('app-two-id', $newLocale->getId()),
            $this->createSnippet('app-two-id', 'other-locale-id'),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        // the snippets of all apps are written in a single update
        static::assertCount(1, $snippetRepository->updates);

        static::assertSame([
            'id' => $snippetOneToUpdate->getId(),
            'localeId' => $previousLocale->getId(),
        ], $snippetRepository->updates[0][0]);

        static::assertSame([
            'id' => $snippetTwoToUpdate->getId(),
            'localeId' => $previousLocale->getId(),
        ], $snippetRepository->updates[0][1]);
    }

    #[DataProvider('localeCodes')]
    public function testUpdatesSnippetsForNewLocaleWithNewLocaleId(string $locale): void
    {
        $localeRepository = new StaticEntityRepository([
            new LocaleCollection([$previousLocale = $this->createLocale('en-GB')]),
            new LocaleCollection([$newLocale = $this->createLocale($locale)]),
        ]);

        $snippetRepository = new StaticEntityRepository([new AppAdministrationSnippetCollection([
            $snippetOneToUpdate = $this->createSnippet('app-one-id', $previousLocale->getId()),
            $snippetTwoToUpdate = $this->createSnippet('app-two-id', $previousLocale->getId()),
        ])]);

        $subscriber = new SystemLanguageChangedSubscriber(
            $localeRepository,
            $snippetRepository
        );

        $subscriber->onSystemLanguageChanged(new SystemLanguageChangeEvent(
            'previous-language-id',
            $previousLocale->getCode(),
            $newLocale->getCode(),
        ));

        // one update carrying the snippet of both apps, not one update per app
        static::assertCount(1, $snippetRepository->updates);
        static::assertSame([
            ['id' => $snippetOneToUpdate->getId(), 'localeId' => $newLocale->getId()],
            ['id' => $snippetTwoToUpdate->getId(), 'localeId' => $newLocale->getId()],
        ], $snippetRepository->updates[0]);
    }

    public static function localeCodes(): \Generator
    {
        yield ['en-US'];
        yield ['it-IT'];
        yield ['es-ES'];
        yield ['fr-FR'];
    }

    private function createLocale(string $code): LocaleEntity
    {
        return (new LocaleEntity())->assign([
            'id' => Uuid::randomHex(),
            'code' => $code,
        ]);
    }

    private function createSnippet(string $appId, string $localeId): AppAdministrationSnippetEntity
    {
        return (new AppAdministrationSnippetEntity())->assign([
            'id' => Uuid::randomHex(),
            'appId' => $appId,
            'localeId' => $localeId,
            'value' => 'snippet-value',
        ]);
    }
}
