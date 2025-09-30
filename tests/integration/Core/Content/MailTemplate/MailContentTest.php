<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('after-sales')]
class MailContentTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURE_FOLDER = __DIR__ . '/../../../../../src/Core/Migration/Fixtures/mails/';
    private const EXCLUDED_DIRECTORIES = [
        'defaultMailFooter',
        'defaultMailHeader',
    ];

    private const DEFAULT_LANGUAGE_CODES = [
        'de-DE',
        'en-GB',
    ];

    private Connection $connection;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->filesystem = new Filesystem();
    }

    public function testMailTemplatesContentMatchesFixture(): void
    {
        $technicalNames = $this->getTechnicalNameFromDirectory();

        foreach ($technicalNames as $technicalName) {
            $templateData = $this->getMailTemplateDataFromDatabase($technicalName);

            $templatesByLanguage = [];
            foreach ($templateData as $data) {
                $templatesByLanguage[$data['code']] = $data;
            }

            foreach (self::DEFAULT_LANGUAGE_CODES as $localeCode) {
                $this->compareContentByLanguage($technicalName, $localeCode, $templatesByLanguage[$localeCode]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getTechnicalNameFromDirectory(): array
    {
        static::assertTrue($this->filesystem->exists(self::FIXTURE_FOLDER));

        $finder = new Finder();
        $finder->in(self::FIXTURE_FOLDER)->depth('== 0')->directories();

        $directories = [];
        foreach ($finder as $directory) {
            if (\in_array($directory->getFilename(), self::EXCLUDED_DIRECTORIES, true)) {
                continue;
            }
            $directories[] = $directory->getFilename();
        }

        return $directories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMailTemplateDataFromDatabase(string $technicalName): array
    {
        $countLanguageCodes = \count(self::DEFAULT_LANGUAGE_CODES);

        $sql = '
            SELECT
                mt_translation.content_html,
                mt_translation.content_plain,
                loc.code
            FROM
                mail_template_type AS mt_type
                LEFT JOIN mail_template AS mt ON mt.mail_template_type_id = mt_type.id
                LEFT JOIN mail_template_translation AS mt_translation ON mt_translation.mail_template_id = mt.id
                LEFT JOIN language AS lang ON  lang.id = mt_translation.language_id
                LEFT JOIN locale AS loc ON loc.id = lang.locale_id
            WHERE
                mt_type.technical_name = :technicalName
                AND mt.system_default = 1
                AND loc.code IN (:defaultLanguages)
        ';

        $data = $this->connection->fetchAllAssociative(
            $sql,
            [
                'technicalName' => $technicalName,
                'defaultLanguages' => self::DEFAULT_LANGUAGE_CODES,
            ],
            ['defaultLanguages' => ArrayParameterType::STRING]
        );

        static::assertCount(
            $countLanguageCodes,
            $data,
            \sprintf(
                'There should be %s languages for template data with the technical name %s',
                $countLanguageCodes,
                $technicalName
            )
        );

        return $data;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function compareContentByLanguage(string $technicalName, string $localeCode, array $templateData): void
    {
        $languageCode = substr($localeCode, 0, 2);
        $fixturePath = self::FIXTURE_FOLDER . $technicalName . '/' . $languageCode;

        $plainFixturePath = $fixturePath . '-plain.html.twig';
        $htmlFixturePath = $fixturePath . '-html.html.twig';

        $plainFixtureContent = $this->filesystem->readFile($plainFixturePath);
        $htmlFixtureContent = $this->filesystem->readFile($htmlFixturePath);

        static::assertIsString($plainFixtureContent, 'Plain fixture content should be a string');
        static::assertIsString($htmlFixtureContent, 'HTML fixture content should be a string');

        static::assertSame(
            $plainFixtureContent,
            $templateData['content_plain'],
            \sprintf('Plain content does not match for template %s and language %s', $technicalName, $localeCode),
        );

        static::assertSame(
            $htmlFixtureContent,
            $templateData['content_html'],
            \sprintf('HTML content does not match for template %s and language %s', $technicalName, $localeCode),
        );
    }
}
