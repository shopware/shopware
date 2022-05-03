<?php declare(strict_types=1);

namespace Locale;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

class LanguageLocaleCodeProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private EntityRepositoryInterface $languageRepository;

    private Context $context;

    public function setUp(): void
    {
        $this->languageLocaleProvider = $this->getContainer()->get(LanguageLocaleCodeProvider::class);
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testGetLocaleForLanguageId(): void
    {
        static::assertEquals('en-GB', $this->languageLocaleProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM));
        static::assertEquals('de-DE', $this->languageLocaleProvider->getLocaleForLanguageId($this->getDeDeLanguageId()));
    }

    public function testGetLocaleForLanguageIdThrowsForNotExistingLanguage(): void
    {
        static::expectException(LanguageNotFoundException::class);
        $this->languageLocaleProvider->getLocaleForLanguageId(Uuid::randomHex());
    }

    public function testGetLocalesForLanguageIds(): void
    {
        $deDeLanguage = $this->getDeDeLanguageId();

        static::assertEquals([
            Defaults::LANGUAGE_SYSTEM => 'en-GB',
            $deDeLanguage => 'de-DE',
        ], $this->languageLocaleProvider->getLocalesForLanguageIds([Defaults::LANGUAGE_SYSTEM, $deDeLanguage]));
    }

    public function testGetLocaleInheritance(): void
    {
        $deDeLanguage = $this->getDeDeLanguage();

        $inheritedLanguageId = Uuid::randomHex();
        $this->languageRepository->create([
            [
                'id' => $inheritedLanguageId,
                'localeId' => $deDeLanguage->getLocaleId(),
                'translationCodeId' => null,
                'name' => 'Test language',
                'parentId' => $deDeLanguage->getId(),
            ],
        ], $this->context);

        static::assertEquals(
            $deDeLanguage->getTranslationCode()->getCode(),
            $this->languageLocaleProvider->getLocaleForLanguageId($inheritedLanguageId)
        );
    }

    public function testGetLocaleInheritanceList(): void
    {
        $deDeLanguage = $this->getDeDeLanguage();

        $inheritedLanguageId = Uuid::randomHex();
        $this->languageRepository->create([
            [
                'id' => $inheritedLanguageId,
                'localeId' => $deDeLanguage->getLocaleId(),
                'translationCodeId' => null,
                'name' => 'Test language',
                'parentId' => $deDeLanguage->getId(),
            ],
        ], $this->context);

        static::assertEquals(
            [
                Defaults::LANGUAGE_SYSTEM => 'en-GB',
                $deDeLanguage->getId() => $deDeLanguage->getTranslationCode()->getCode(),
                $inheritedLanguageId => $deDeLanguage->getTranslationCode()->getCode(),
            ],
            $this->languageLocaleProvider->getLocalesForLanguageIds([
                Defaults::LANGUAGE_SYSTEM,
                $deDeLanguage->getId(),
                $inheritedLanguageId,
            ])
        );
    }

    private function getDeDeLanguage(): LanguageEntity
    {
        $repository = $this->getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.translationCode.code', 'de-DE'));
        $criteria->addAssociation('translationCode');

        /** @var LanguageEntity $language */
        $language = $repository->search($criteria, $this->context)->first();

        return $language;
    }
}
