<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1600778848AddOrderMails;

class Migration1600778848AddOrderMailsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const INITIAL = 'initial';

    /**
     * @dataProvider providerEnglish
     */
    public function testEnglishAsDefault(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        $this->createLanguages($ids);

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update($this->getContainer()->get(Connection::class));

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertTrue(strpos($mail['content_html'], $updated) !== false);
            static::assertTrue(strpos($mail['content_plain'], $updated) !== false);
        }
    }

    /**
     * @dataProvider providerGerman
     */
    public function testGermanAsDefault(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        $this->getContainer()->get(Connection::class)
            ->executeUpdate(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'locale' => Uuid::fromHexToBytes($this->getLocaleId('de-DE'))]
            );

        $this->createLanguages($ids);

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update($this->getContainer()->get(Connection::class));

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertTrue(strpos($mail['content_html'], $updated) !== false);
            static::assertTrue(strpos($mail['content_plain'], $updated) !== false);
        }
    }

    /**
     * @dataProvider providerEnglish
     */
    public function testFranceAsDefault(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        $this->getContainer()->get(Connection::class)
            ->executeUpdate(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'locale' => Uuid::fromHexToBytes($this->getLocaleId('fr-FR'))]
            );

        $this->createLanguages($ids);

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update($this->getContainer()->get(Connection::class));

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertTrue(strpos($mail['content_html'], $updated) !== false);
            static::assertTrue(strpos($mail['content_plain'], $updated) !== false);
        }
    }

    /**
     * @dataProvider providerOnlyFrance
     */
    public function testOnlyFrance(array $initial, array $expected, IdsCollection $ids): void
    {
        $this->resetMails();

        $this->getContainer()->get(Connection::class)
            ->executeUpdate(
                'UPDATE `language` SET locale_id = :locale WHERE id = :id',
                ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), 'locale' => Uuid::fromHexToBytes($this->getLocaleId('fr-FR'))]
            );

        $this->createMail($initial, $ids);

        $migration = new Migration1600778848AddOrderMails();
        $migration->update($this->getContainer()->get(Connection::class));

        $mails = $this->getMails();

        foreach ($expected as $key => $updated) {
            static::assertArrayHasKey($key, $mails);

            $mail = $mails[$key];

            static::assertTrue(strpos($mail['content_html'], $updated) !== false);
            static::assertTrue(strpos($mail['content_plain'], $updated) !== false);
        }
    }

    public function providerOnlyFrance()
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

    public function providerGerman()
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

    public function providerEnglish()
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

        $this->getContainer()->get(Connection::class)
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
            $this->getContainer()->get(Connection::class)
                ->insert('mail_template_translation', $translation);
        }
    }

    private function resetMails(): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->executeUpdate('DELETE FROM mail_template');
    }

    private function createLanguages(IdsCollection $ids): void
    {
        $data = [
            [
                'id' => $ids->create('german'),
                'name' => 'test',
                'localeId' => $this->getLocaleId('de-DE'),
                'translationCode' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'te-te',
                    'name' => 'Test locale',
                    'territory' => 'test',
                ],
            ],
            [
                'id' => $ids->create('en-2'),
                'name' => 'test',
                'localeId' => $this->getLocaleId('en-GB'),
                'translationCode' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'fr-te',
                    'name' => 'Test locale',
                    'territory' => 'test',
                ],
            ],
        ];

        $this->getContainer()->get('language.repository')
            ->create($data, Context::createDefaultContext());
    }

    private function getTypeId(string $type): string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => $type]);
    }

    private function getLocaleId(string $code): string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(id)) FROM locale WHERE code = :code', ['code' => $code]);
    }

    private function getMails(): array
    {
        $mails = $this->getContainer()->get(Connection::class)
            ->fetchAll("
                SELECT
                    CONCAT(LOWER(HEX(mail_template.id)), '.', LOWER(HEX(mail_template_translation.language_id))) as `array_key`,
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
            ");

        return FetchModeHelper::groupUnique($mails);
    }

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
