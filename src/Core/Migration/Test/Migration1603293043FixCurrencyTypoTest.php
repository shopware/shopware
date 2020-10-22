<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1603293043FixCurrencyTypo;

class Migration1603293043FixCurrencyTypoTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCurrencyName(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $englishLanguageId = $connection->createQueryBuilder()
            ->select('lang.id')
            ->from('language', 'lang')
            ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
            ->where('loc.code = :englishLocale')
            ->setParameter(':englishLocale', 'en-GB')
            ->execute()
            ->fetchColumn();

        if (!$englishLanguageId) {
            static::expectNotToPerformAssertions();

            return;
        }

        $kronaQuery = $connection->createQueryBuilder()
            ->select('ct.name')
            ->from('currency_translation', 'ct')
            ->where('ct.name = :kronaEnglish')
            ->andWhere('ct.language_id = :englishLanguageId')
            ->setParameter('englishLanguageId', $englishLanguageId)
            ->setParameter('kronaEnglish', 'Swedish Krone');

        $kronaWithTypo = $kronaQuery->execute()->fetchColumn();

        if (!$kronaWithTypo) {
            static::expectNotToPerformAssertions();

            return;
        }

        $migration = new Migration1603293043FixCurrencyTypo();
        $migration->updateDestructive($connection);

        $kronaQuery->setParameter('kronaEnglish', 'Swedish Krona');

        $krona = $kronaQuery->execute()->fetchColumn();

        static::assertSame('Swedish krona', $krona);
    }
}
