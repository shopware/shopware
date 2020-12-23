<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1582011195FixCountryStateGermanTranslation;

class Migration1582011195FixCountryStateGermanTranslationTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoGermanLanguageId(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $resultStatement = $this->createMock(Statement::class);
        $resultStatement->method('fetchColumn')->willReturn(false);

        // early return because of no german language id by locale de-DE
        $resultStatement->expects(static::never())->method('fetchAll');

        $connectionMock->method('executeQuery')->willReturn($resultStatement);

        $queryBuilder = new QueryBuilder($connectionMock);
        $connectionMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $migration = new Migration1582011195FixCountryStateGermanTranslation();
        $migration->update($connectionMock);
    }

    public function testMigrationWorks(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

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
            ->setParameter(':germanLocale', 'de-DE')
            ->execute()
            ->fetchColumn();

        $translationQuery = $connection->createQueryBuilder()
            ->select('state.short_code, state.id, state_translation.name')
            ->from('country_state', 'state')
            ->innerJoin(
                'state',
                'country_state_translation',
                'state_translation',
                'state.id = state_translation.country_state_id AND state_translation.language_id = :germanLanguageId'
            )->where('state.short_code IN (:shortCodes)')
            ->setParameter(':germanLanguageId', $germanLanguageId)
            ->setParameter(':shortCodes', array_keys($testTranslations), Connection::PARAM_STR_ARRAY);

        $translations = $translationQuery->execute()->fetchAll();

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

        $afterTranslations = $translationQuery->execute()->fetchAll();

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
