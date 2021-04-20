<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AdministrationControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use AdminFunctionalTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setup(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->connection = $this->getContainer()->get(Connection::class);
        $newLanguageId = $this->insertOtherLanguage();
        $this->createSearchConfigFieldForNewLanguage($newLanguageId);
    }

    public function testSnippetRoute(): void
    {
        $this->getBrowser()->request('GET', '/api/_admin/snippets?locale=de-DE');
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($content);

        $response = json_decode($content, true);
        static::assertArrayHasKey('de-DE', $response);
        static::assertArrayHasKey('en-GB', $response);
    }

    public function testResetExcludedSearchTerm(): void
    {
        /** @var string $coreDir */
        $coreDir = $this->getContainer()->getParameter('kernel.shopware_core_dir');
        $defaultExcludedTermEn = require $coreDir . '/Migration/Fixtures/stopwords/en.php';
        $defaultExcludedTermDe = require $coreDir . '/Migration/Fixtures/stopwords/de.php';

        $languageIds = array_column($this->connection->fetchAll('SELECT `language`.id FROM `language`'), 'id');
        foreach ($languageIds as $languageId) {
            $isoCode = $this->getLanguageCode($languageId);
            $this->connection->executeUpdate(
                'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `language_id` = :languageId',
                [
                    'excludedTerms' => json_encode(['me', 'my', 'myself']),
                    'languageId' => $languageId,
                ]
            );

            $this->getBrowser()->setServerParameter('HTTP_sw-language-id', Uuid::fromBytesToHex($languageId));
            $this->getBrowser()->request('POST', '/api/_admin/reset-excluded-search-term');
            $response = $this->getBrowser()->getResponse();

            $excludedTerms = json_decode($this->connection->executeQuery(
                'SELECT `excluded_terms` FROM `product_search_config` WHERE `language_id` = :languageId',
                ['languageId' => $languageId]
            )->fetchColumn(), true);

            static::assertEquals(200, $response->getStatusCode());

            switch ($isoCode) {
                case 'en-GB':
                    static::assertEquals($defaultExcludedTermEn, $excludedTerms);

                    break;
                case 'de-DE':
                    static::assertEquals($defaultExcludedTermDe, $excludedTerms);

                    break;
                default:
                    static::assertEmpty($excludedTerms);
            }
        }
    }

    public function testResetExcludedSearchTermIncorrectLanguageId(): void
    {
        $this->getBrowser()->setServerParameter('HTTP_sw-language-id', Uuid::randomHex());
        $this->getBrowser()->request('POST', '/api/_admin/reset-excluded-search-term');

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(412, $response->getStatusCode());
    }

    private function insertOtherLanguage(): string
    {
        $langId = array_column($this->connection->executeQuery(
            'SELECT id FROM `language` WHERE `name` = :langName',
            [
                'langName' => 'Vietnamese',
            ]
        )->fetchAll(), 'id');

        $localeId = array_column($this->connection->executeQuery(
            'SELECT id FROM `locale` WHERE `code` = :code',
            [
                'code' => 'vi-VN',
            ]
        )->fetchAll(), 'id');

        if ($langId) {
            return $langId[0];
        }

        $newLanguageId = Uuid::randomBytes();
        $statement = $this->connection->prepare('INSERT INTO `language` (`id`, `name`, `locale_id`, `translation_code_id`, `created_at`)
            VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$newLanguageId, 'Vietnamese', $localeId[0], $localeId[0], '2021-04-01 04:41:12.045']);

        return $newLanguageId;
    }

    private function createSearchConfigFieldForNewLanguage(string $newLanguageId): void
    {
        $configId = array_column($this->connection->executeQuery(
            'SELECT id FROM `product_search_config` WHERE `language_id` = :languageId',
            [
                'languageId' => $newLanguageId,
            ]
        )->fetchAll(), 'id');

        if (!$configId) {
            $newConfigId = Uuid::randomBytes();
            $statement = $this->connection->prepare('INSERT INTO `product_search_config` (`id`, `language_id`, `and_logic`, `min_search_length`, `created_at`)
                VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$newConfigId, $newLanguageId, 0, 2, '2021-04-01 04:41:12.045']);
        }
    }

    private function getLanguageCode(string $languageId): ?string
    {
        return $this->connection->fetchColumn(
            '
            SELECT `locale`.code FROM `language`
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE `language`.id = :id',
            ['id' => $languageId]
        );
    }
}
