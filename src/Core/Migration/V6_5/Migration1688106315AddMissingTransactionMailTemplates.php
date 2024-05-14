<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('buyers-experience')]
class Migration1688106315AddMissingTransactionMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public const AUTHORIZED_TYPE = 'order_transaction.state.authorized';

    public const CHARGEBACK_TYPE = 'order_transaction.state.chargeback';

    public const UNCONFIRMED_TYPE = 'order_transaction.state.unconfirmed';

    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1688106315;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $mails = [
            self::AUTHORIZED_TYPE => [
                'type' => [
                    'technicalName' => self::AUTHORIZED_TYPE,
                    'availableEntities' => '{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel","editOrderUrl":null}',
                ],
                'template' => [
                    'htmlDe' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-html.html.twig'),
                    'plainDe' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/de-plain.html.twig'),
                    'htmlEn' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-html.html.twig'),
                    'plainEn' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.authorized/en-plain.html.twig'),
                ],
                'translations' => [
                    'en' => [
                        'name' => 'Enter payment state: Authorized',
                        'subject' => 'The order at {{ salesChannel.name }} was authorized',
                        'description' => 'Shopware Basis Template',
                    ],
                    'de' => [
                        'name' => 'Eintritt Zahlungsstatus: Autorisiert',
                        'subject' => 'Die Bestellung bei {{ salesChannel.name }} wurde autorisiert',
                        'description' => 'Shopware Basis Template',
                    ],
                ],
            ],
            self::CHARGEBACK_TYPE => [
                'type' => [
                    'technicalName' => self::CHARGEBACK_TYPE,
                    'availableEntities' => '{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel","editOrderUrl":null}',
                ],
                'template' => [
                    'htmlDe' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-html.html.twig'),
                    'plainDe' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/de-plain.html.twig'),
                    'htmlEn' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-html.html.twig'),
                    'plainEn' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.chargeback/en-plain.html.twig'),
                ],
                'translations' => [
                    'en' => [
                        'name' => 'Enter payment state: Chargeback',
                        'subject' => 'Chargeback for your order with {{ salesChannel.name }}',
                        'description' => 'Shopware Basis Template',
                    ],
                    'de' => [
                        'name' => 'Eintritt Zahlungsstatus: Rückbuchung',
                        'subject' => 'Rückbuchung für Ihre Bestellung bei {{ salesChannel.name }}',
                        'description' => 'Shopware Basis Template',
                    ],
                ],
            ],
            self::UNCONFIRMED_TYPE => [
                'type' => [
                    'technicalName' => self::UNCONFIRMED_TYPE,
                    'availableEntities' => '{"order":"order","previousState":"state_machine_state","newState":"state_machine_state","salesChannel":"sales_channel","editOrderUrl":null}',
                ],
                'template' => [
                    'htmlDe' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-html.html.twig'),
                    'plainDe' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/de-plain.html.twig'),
                    'htmlEn' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-html.html.twig'),
                    'plainEn' => file_get_contents(__DIR__ . '/../Fixtures/mails/order_transaction.state.unconfirmed/en-plain.html.twig'),
                ],
                'translations' => [
                    'en' => [
                        'name' => 'Enter payment state: Unconfirmed',
                        'subject' => 'Your order with {{ salesChannel.name }} is unconfirmed',
                        'description' => 'Shopware Basis Template',
                    ],
                    'de' => [
                        'name' => 'Ihre Bestellung bei {{ salesChannel.name }} ist unbestätigt',
                        'subject' => '',
                        'description' => 'Shopware Basis Template',
                    ],
                ],
            ],
        ];

        foreach ($mails as $mail) {
            $typeName = $mail['type']['technicalName'];

            $templateTypeId = $this->insertMailTemplateTypeData($typeName, $mail, $connection);
            $this->insertMailTemplateData($templateTypeId, $mail, $connection);
            $this->updateMailTemplateContent($typeName, $mail, $connection);
        }
    }

    private function fetchLanguageIdByName(string $name, Connection $connection): ?string
    {
        try {
            $result = $connection->fetchOne(
                'SELECT id FROM `language` WHERE `name` = :languageName',
                ['languageName' => $name]
            );

            if (!\is_string($result)) {
                return null;
            }

            return $result;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $mail
     *
     * @throws Exception
     */
    private function insertMailTemplateTypeData(string $typeName, array $mail, Connection $connection): string
    {
        $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => $typeName]);

        if ($templateTypeId) {
            return \is_string($templateTypeId) ? $templateTypeId : '';
        }

        $templateTypeId = Uuid::randomBytes();
        $connection->insert(
            'mail_template_type',
            [
                'id' => $templateTypeId,
                'technical_name' => $typeName,
                'available_entities' => $mail['type']['availableEntities'],
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $defaultLanguageId,
                    'name' => $mail['translations']['en']['name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $englishLanguageId,
                    'name' => $mail['translations']['en']['name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($germanLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $germanLanguageId,
                    'name' => $mail['translations']['de']['name'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $templateTypeId;
    }

    /**
     * @param array<string, mixed> $mail
     *
     * @throws Exception
     */
    private function insertMailTemplateData(string $templateTypeId, array $mail, Connection $connection): void
    {
        $templateId = Uuid::randomBytes();
        $connection->insert(
            'mail_template',
            [
                'id' => $templateId,
                'mail_template_type_id' => $templateTypeId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => $mail['translations']['en']['subject'],
                    'description' => $mail['translations']['en']['description'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $defaultLanguageId,
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => $mail['translations']['en']['subject'],
                    'description' => $mail['translations']['en']['description'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $englishLanguageId,
                ]
            );
        }

        if ($germanLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => $mail['translations']['de']['subject'],
                    'description' => $mail['translations']['de']['description'],
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $germanLanguageId,
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $mail
     */
    private function updateMailTemplateContent(string $typeName, array $mail, Connection $connection): void
    {
        $update = new MailUpdate(
            $typeName,
            $mail['template']['plainEn'],
            $mail['template']['htmlEn'],
            $mail['template']['plainDe'],
            $mail['template']['htmlEn'],
        );

        $this->updateMail($update, $connection);
    }
}
