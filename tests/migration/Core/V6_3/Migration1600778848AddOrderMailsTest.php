<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1600778848AddOrderMails;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1600778848AddOrderMails
 *
 * @phpstan-type Mail array{id-key: string, type: string, language: string, content: string, system_default: int, updated_at: ?\DateTimeInterface, translations: array<string, array<string, mixed>>}
 */
class Migration1600778848AddOrderMailsTest extends TestCase
{
    use MigrationTestTrait;

    private const INITIAL = 'initial';

    /**
     * @dataProvider providerEnglish
     *
     * @param Mail $initial
     * @param array<string, string> $expected
     */
    public function testEnglishAsDefault(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        $this->createLanguages($ids);

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update(KernelLifecycleManager::getConnection());

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertStringContainsString($updated, $mail['content_html']);
            static::assertStringContainsString($updated, $mail['content_plain']);
        }
    }

    /**
     * @dataProvider providerGerman
     *
     * @param Mail $initial
     * @param array<string, string> $expected
     */
    public function testGermanAsDefault(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        KernelLifecycleManager::getConnection()
            ->executeStatement(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'locale' => $this->getLocaleId('de-DE')]
            );

        $this->createLanguages($ids);

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update(KernelLifecycleManager::getConnection());

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertStringContainsString($updated, $mail['content_html']);
            static::assertStringContainsString($updated, $mail['content_plain']);
        }
    }

    /**
     * @dataProvider providerEnglish
     *
     * @param Mail $initial
     * @param array<string, string> $expected
     */
    public function testFranceAsDefault(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        KernelLifecycleManager::getConnection()
            ->executeStatement(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'locale' => $this->getLocaleId('fr-FR')]
            );

        $this->createLanguages($ids);

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update(KernelLifecycleManager::getConnection());

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertStringContainsString($updated, $mail['content_html']);
            static::assertStringContainsString($updated, $mail['content_plain']);
        }
    }

    /**
     * @dataProvider providerOnlyFrance
     *
     * @param Mail $initial
     * @param array<string, string> $expected
     */
    public function testOnlyFrance(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        KernelLifecycleManager::getConnection()
            ->executeStatement(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'locale' => $this->getLocaleId('fr-FR')]
            );

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update(KernelLifecycleManager::getConnection());

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertStringContainsString($updated, $mail['content_html']);
            static::assertStringContainsString($updated, $mail['content_plain']);
        }
    }

    public function providerOnlyFrance(): \Generator
    {
        $ids = new IdsCollection();

        $date = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $mails = [
            'order-mail' => $this->createOrderMail($ids),
            'payment-confirmed' => $this->createPaymentConfirmed($ids),
            'payment-cancelled' => $this->createPaymentCancelled($ids),
        ];

        foreach ($mails as &$temp) {
            $temp['translations'] = [
                Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => null],
            ];
        }

        foreach ($mails as $key => $mail) {
            yield $key . ': all update' => [
                $mail,
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => 'Shipping costs:',
                ],
                $ids,
            ];

            yield $key . ': default as updated_at' => [
                array_replace_recursive($mail, [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => $date],
                    ],
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                ],
                $ids,
            ];

            yield $key . ': no update because no system default' => [
                array_replace_recursive($mail, [
                    'system_default' => 0,
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                ],
                $ids,
            ];

            yield $key . ': no update, because updated_at on mail_template' => [
                array_replace_recursive($mail, [
                    'updated_at' => $date,
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                ],
                $ids,
            ];
        }
    }

    public function providerGerman(): \Generator
    {
        $ids = new IdsCollection();

        $date = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $mails = [
            'order-mail' => $this->createOrderMail($ids),
            'payment-confirmed' => $this->createPaymentConfirmed($ids),
            'payment-cancelled' => $this->createPaymentCancelled($ids),
        ];

        foreach ($mails as $key => $mail) {
            yield $key . ': all update' => [
                $mail,
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('german') => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('en-2') => 'Shipping costs:',
                ],
                $ids,
            ];

            yield $key . ': de has updated_at' => [
                array_replace_recursive($mail, [
                    'translations' => [
                        $ids->get('german') => ['content' => self::INITIAL, 'updated_at' => $date],
                    ],
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('german') => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('en-2') => 'Shipping costs:',
                ],
                $ids,
            ];

            yield $key . ': default as updated_at' => [
                array_replace_recursive($mail, [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => $date],
                    ],
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('en-2') => 'Shipping costs:',
                ],
                $ids,
            ];

            yield $key . ': en and en-2 has updated_at' => [
                array_replace_recursive($mail, [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => $date],
                        $ids->get('en-2') => ['content' => self::INITIAL, 'updated_at' => $date],
                    ],
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('en-2') => self::INITIAL,
                ],
                $ids,
            ];

            yield $key . ': no update because no system default' => [
                array_replace_recursive($mail, [
                    'system_default' => 0,
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('en-2') => self::INITIAL,
                ],
                $ids,
            ];

            yield $key . ': no update, because updated_at on mail_template' => [
                array_replace_recursive($mail, [
                    'updated_at' => $date,
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('en-2') => self::INITIAL,
                ],
                $ids,
            ];
        }
    }

    public function providerEnglish(): \Generator
    {
        $ids = new IdsCollection();

        $date = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $mails = [
            'order-mail' => $this->createOrderMail($ids),
            'payment-confirmed' => $this->createPaymentConfirmed($ids),
            'payment-cancelled' => $this->createPaymentCancelled($ids),
        ];

        foreach ($mails as $key => $mail) {
            yield $key . ': all update' => [
                $mail,
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => 'Shipping costs:',
                    $ids->get($key) . '.' . $ids->get('german') => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('en-2') => 'Shipping costs:',
                ],
                $ids,
            ];

            yield $key . ': de has updated_at' => [
                array_replace_recursive($mail, [
                    'translations' => [
                        $ids->get('german') => ['content' => self::INITIAL, 'updated_at' => $date],
                    ],
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => 'Shipping costs:',
                    $ids->get($key) . '.' . $ids->get('german') => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('en-2') => 'Shipping costs:',
                ],
                $ids,
            ];

            yield $key . ': default as updated_at' => [
                array_replace_recursive($mail, [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => $date],
                    ],
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('en-2') => 'Shipping costs:',
                ],
                $ids,
            ];

            yield $key . ': en and en-2 has updated_at' => [
                array_replace_recursive($mail, [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => $date],
                        $ids->get('en-2') => ['content' => self::INITIAL, 'updated_at' => $date],
                    ],
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => 'Versandkosten:',
                    $ids->get($key) . '.' . $ids->get('en-2') => self::INITIAL,
                ],
                $ids,
            ];

            yield $key . ': no update because no system default' => [
                array_replace_recursive($mail, [
                    'system_default' => 0,
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('en-2') => self::INITIAL,
                ],
                $ids,
            ];

            yield $key . ': no update, because updated_at on mail_template' => [
                array_replace_recursive($mail, [
                    'updated_at' => $date,
                ]),
                [
                    $ids->get($key) . '.' . Defaults::LANGUAGE_SYSTEM => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('german') => self::INITIAL,
                    $ids->get($key) . '.' . $ids->get('en-2') => self::INITIAL,
                ],
                $ids,
            ];
        }
    }

    /**
     * @param Mail $mail
     */
    private function createMail(array $mail, IdsCollection $ids): void
    {
        $id = $ids->create($mail['id-key']);

        $type = $mail['type'];

        $data = [
            'id' => Uuid::fromHexToBytes($id),
            'system_default' => $mail['system_default'],
            'updated_at' => $mail['updated_at'],
            'mail_template_type_id' => $this->getTypeId($type),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        KernelLifecycleManager::getConnection()
            ->insert('mail_template', $data);

        foreach ($mail['translations'] as $language => $translation) {
            $translation = [
                'mail_template_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes($language),
                'content_html' => $translation['content'],
                'content_plain' => $translation['content'],
                'updated_at' => $translation['updated_at'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
            KernelLifecycleManager::getConnection()
                ->insert('mail_template_translation', $translation);
        }
    }

    private function resetMails(): void
    {
        KernelLifecycleManager::getConnection()->executeStatement('DELETE FROM mail_template');
    }

    private function createLanguages(IdsCollection $ids): void
    {
        $localeData = [
            [
                'id' => Uuid::fromHexToBytes($ids->create('firstLocale')),
                'code' => 'te-te',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::fromHexToBytes($ids->create('secondLocale')),
                'code' => 'fr-te',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        $languageData = [
            [
                'id' => Uuid::fromHexToBytes($ids->create('german')),
                'name' => 'test',
                'locale_id' => $this->getLocaleId('de-DE'),
                'translation_code_id' => Uuid::fromHexToBytes($ids->get('firstLocale')),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::fromHexToBytes($ids->create('en-2')),
                'name' => 'test',
                'locale_id' => $this->getLocaleId('en-GB'),
                'translation_code_id' => Uuid::fromHexToBytes($ids->get('secondLocale')),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        $connection = KernelLifecycleManager::getConnection();
        $connection->insert('locale', $localeData[0]);
        $connection->insert('locale', $localeData[1]);

        $connection->insert('language', $languageData[0]);
        $connection->insert('language', $languageData[1]);
    }

    private function getTypeId(string $type): string
    {
        return KernelLifecycleManager::getConnection()
            ->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => $type]);
    }

    private function getLocaleId(string $code): string
    {
        return KernelLifecycleManager::getConnection()
            ->fetchOne('SELECT id FROM locale WHERE code = :code', ['code' => $code]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getMails(): array
    {
        $mails = KernelLifecycleManager::getConnection()
            ->fetchAllAssociative('
                SELECT
                    CONCAT(LOWER(HEX(mail_template.id)), \'.\', LOWER(HEX(mail_template_translation.language_id))) as `array_key`,
                    LOWER(HEX(mail_template.id)) as `mail_template_id`,
                    LOWER(HEX(mail_template_translation.language_id)) as `language_id`,
                    mail_template_type.technical_name,
                    mail_template_translation.content_html,
                    mail_template_translation.content_plain
                FROM mail_template
                    INNER JOIN mail_template_translation
                        ON mail_template.id = mail_template_translation.mail_template_id
                    INNER JOIN mail_template_type
                        ON mail_template.mail_template_type_id = mail_template_type.id
            ');

        return FetchModeHelper::groupUnique($mails);
    }

    /**
     * @return Mail
     */
    private function createOrderMail(IdsCollection $ids): array
    {
        return [
            'id-key' => 'order-mail',
            'type' => 'order_confirmation_mail',
            'language' => Defaults::LANGUAGE_SYSTEM,
            'content' => 'INITIAL',
            'system_default' => 1,
            'updated_at' => null,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => null],
                $ids->get('german') => ['content' => self::INITIAL, 'updated_at' => null],
                $ids->get('en-2') => ['content' => self::INITIAL, 'updated_at' => null],
            ],
        ];
    }

    /**
     * @return Mail
     */
    private function createPaymentConfirmed(IdsCollection $ids): array
    {
        return [
            'id-key' => 'payment-confirmed',
            'type' => 'order_transaction.state.paid',
            'language' => Defaults::LANGUAGE_SYSTEM,
            'content' => 'INITIAL',
            'system_default' => 1,
            'updated_at' => null,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => null],
                $ids->get('german') => ['content' => self::INITIAL, 'updated_at' => null],
                $ids->get('en-2') => ['content' => self::INITIAL, 'updated_at' => null],
            ],
        ];
    }

    /**
     * @return Mail
     */
    private function createPaymentCancelled(IdsCollection $ids): array
    {
        return [
            'id-key' => 'payment-cancelled',
            'type' => 'order_transaction.state.cancelled',
            'language' => Defaults::LANGUAGE_SYSTEM,
            'content' => 'INITIAL',
            'system_default' => 1,
            'updated_at' => null,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['content' => self::INITIAL, 'updated_at' => null],
                $ids->get('german') => ['content' => self::INITIAL, 'updated_at' => null],
                $ids->get('en-2') => ['content' => self::INITIAL, 'updated_at' => null],
            ],
        ];
    }
}
