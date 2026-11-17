<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\SearchConfigRankingChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class SearchConfigRankingCheckerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private SearchConfigRankingChecker $checker;

    private string $germanLanguageId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->checker = new SearchConfigRankingChecker($this->connection);

        $germanLanguageId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(language.id)) FROM language INNER JOIN locale ON locale.id = language.locale_id WHERE locale.code = \'de-DE\''
        );
        static::assertIsString($germanLanguageId);
        $this->germanLanguageId = $germanLanguageId;
    }

    public function testDefaultConfigHasNoNegativeRanking(): void
    {
        static::assertFalse($this->checker->hasNegativeRanking(Defaults::LANGUAGE_SYSTEM));
        static::assertFalse($this->checker->hasNegativeRanking($this->germanLanguageId));
    }

    public function testDetectsNegativeRankingOfSearchableField(): void
    {
        $this->setRanking($this->germanLanguageId, 'name', -100, searchable: true);

        static::assertTrue($this->checker->hasNegativeRanking($this->germanLanguageId));
        static::assertFalse($this->checker->hasNegativeRanking(Defaults::LANGUAGE_SYSTEM));
    }

    public function testIgnoresNegativeRankingOfNotSearchableField(): void
    {
        $this->setRanking($this->germanLanguageId, 'name', -100, searchable: false);

        static::assertFalse($this->checker->hasNegativeRanking($this->germanLanguageId));
    }

    public function testDetectsNegativeRankingOfSystemLanguageConfigUsedAsFallback(): void
    {
        $this->setRanking(Defaults::LANGUAGE_SYSTEM, 'name', -100, searchable: true);

        static::assertTrue($this->checker->hasNegativeRanking(Uuid::randomHex()));
    }

    private function setRanking(string $languageId, string $field, int $ranking, bool $searchable): void
    {
        $updated = $this->connection->executeStatement(
            'UPDATE product_search_config_field configField
            INNER JOIN product_search_config config ON config.id = configField.product_search_config_id
            SET configField.ranking = :ranking, configField.searchable = :searchable
            WHERE config.language_id = :languageId AND configField.field = :field',
            [
                'ranking' => $ranking,
                'searchable' => (int) $searchable,
                'languageId' => Uuid::fromHexToBytes($languageId),
                'field' => $field,
            ]
        );

        static::assertSame(1, $updated);
    }
}
