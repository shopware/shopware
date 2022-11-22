<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1582011195FixCountryStateGermanTranslation;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_3\Migration1582011195FixCountryStateGermanTranslation
 */
class Migration1582011195FixCountryStateGermanTranslationTest extends TestCase
{
    public function testNoGermanLanguageId(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $resultStatement = $this->createMock(Result::class);
        $resultStatement->method('fetchOne')->willReturn(false);

        // early return because of no german language id by locale de-DE
        $resultStatement->expects(static::never())->method('fetchAllAssociative');

        $connectionMock->method('executeQuery')->willReturn($resultStatement);

        $queryBuilder = new QueryBuilder($connectionMock);
        $connectionMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $migration = new Migration1582011195FixCountryStateGermanTranslation();
        $migration->update($connectionMock);
    }

    public function testMigrationWorks(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $testTranslations = [
            // custom modified
            'DE-SN' => 'SaxonyTest',
            // already translated
            'DE-ST' => 'Sachsen-Anhalt',
            // no translation available
            'DE-SH' => 'Schleswig-Holstein',
            // english translation
            'DE-TH' => 'Thuringia',
        ];

        $germanLanguageId = $connection->createQueryBuilder()
            ->select('lang.id')
            ->from('language', 'lang')
            ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
            ->where('loc.code = :germanLocale')
            ->setParameter('germanLocale', 'de-DE')
            ->executeQuery()
            ->fetchOne();

        $translationQuery = $connection->createQueryBuilder()
            ->select('state.short_code, state.id, state_translation.name')
            ->from('country_state', 'state')
            ->innerJoin(
                'state',
                'country_state_translation',
                'state_translation',
                'state.id = state_translation.country_state_id AND state_translation.language_id = :germanLanguageId'
            )->where('state.short_code IN (:shortCodes)')
            ->setParameter('germanLanguageId', $germanLanguageId)
            ->setParameter('shortCodes', array_keys($testTranslations), Connection::PARAM_STR_ARRAY);

        $translations = $translationQuery->executeQuery()->fetchAllAssociative();

        foreach ($translations as $translation) {
            $connection->update(
                'country_state_translation',
                ['name' => $testTranslations[$translation['short_code']]],
                [
                    'country_state_id' => $translation['id'],
                    'language_id' => $germanLanguageId,
                ]
            );
        }

        $migration = new Migration1582011195FixCountryStateGermanTranslation();
        $migration->update($connection);

        $afterTranslations = $translationQuery->executeQuery()->fetchAllAssociative();

        foreach ($afterTranslations as $afterTranslation) {
            if (\in_array($afterTranslation['short_code'], ['DE-SN', 'DE-SH', 'DE-ST'], true)) {
                static::assertSame($afterTranslation['name'], $testTranslations[$afterTranslation['short_code']]);
            } elseif ($afterTranslation['short_code'] === 'DE-TH') {
                static::assertNotSame($afterTranslation['name'], $testTranslations['DE-TH']);
                static::assertSame($afterTranslation['name'], 'Thüringen');
            } else {
                static::fail('should be in one of these if clauses');
            }
        }
    }
}
