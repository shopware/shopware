<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1660814397UpdateOrderCancelledMailTemplate;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1660814397UpdateOrderCancelledMailTemplate
 */
class Migration1660814397UpdateOrderCancelledMailTemplateTest extends TestCase
{
    use MigrationTestTrait;

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function testOrderCancelledTemplateIsUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1660814397UpdateOrderCancelledMailTemplate();
        $migration->update($connection);

        $deLangId = $this->fetchDeLanguageId($connection);
        $enLangId = $this->fetchEnLanguageId($connection);
        static::assertNotNull($deLangId);
        static::assertNotNull($enLangId);

        $orderCancelledTemplateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED);
        static::assertNotNull($orderCancelledTemplateId);

        $sqlString = 'SELECT `subject`, `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $orderCancelledTemplateTranslation = $connection->fetchAssociative($sqlString, [
            'langId' => $deLangId,
            'templateId' => $orderCancelledTemplateId,
        ]);

        if (!empty($orderCancelledTemplateTranslation)) {
            static::assertEquals($this->getOrderCancelledHtmlTemplateDe(), $orderCancelledTemplateTranslation['content_html']);
            static::assertEquals($this->getOrderCancelledPlainTemplateDe(), $orderCancelledTemplateTranslation['content_plain']);
        }

        $orderCancelledTemplateTranslation = $connection->fetchAssociative($sqlString, [
            'langId' => $enLangId,
            'templateId' => $orderCancelledTemplateId,
        ]);

        if (!empty($orderCancelledTemplateTranslation)) {
            static::assertEquals($this->getOrderCancelledHtmlTemplateEn(), $orderCancelledTemplateTranslation['content_html']);
            static::assertEquals($this->getOrderCancelledPlainTemplateEn(), $orderCancelledTemplateTranslation['content_plain']);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $type): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $type])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    /**
     * @throws Exception
     */
    private function fetchDeLanguageId(Connection $connection): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'de-DE']) ?: null;
    }

    /**
     * @throws Exception
     */
    private function fetchEnLanguageId(Connection $connection): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'en-GB']) ?: null;
    }

    private function getOrderCancelledHtmlTemplateDe(): string
    {
        return <<<'EOF'
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/>
                    <br/>
                    der Bestellstatus für Ihre Bestellung bei {{ salesChannel.translated.name }} (Number: {{ order.orderNumber }}) vom {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-DE') }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{ order.stateMachineState.translated.name }}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>

            EOF;
    }

    private function getOrderCancelledPlainTemplateDe(): string
    {
        return <<<'EOF'
            {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

            der Bestellstatus für Ihre Bestellung bei {{ salesChannel.translated.name }} (Number: {{ order.orderNumber }}) vom {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-DE') }} hat sich geändert!
            Die Bestellung hat jetzt den Bestellstatus: {{ order.stateMachineState.translated.name }}.

            Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
            Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.

            EOF;
    }

    private function getOrderCancelledHtmlTemplateEn(): string
    {
        return <<<'EOF'
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},<br/>
                    <br/>
                    the status of your order at {{ salesChannel.translated.name }} (Number: {{ order.orderNumber }}) on {{ order.orderDateTime|format_datetime('medium', 'short', locale='en-GB') }} has changed.<br/>
                    <strong>The new status is as follows: {{ order.stateMachineState.translated.name }}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>

            EOF;
    }

    private function getOrderCancelledPlainTemplateEn(): string
    {
        return <<<'EOF'
            {% if order.orderCustomer.salutation %}{{ order.orderCustomer.salutation.translated.letterName ~ ' ' }}{% endif %}{{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},

            the status of your order at {{ salesChannel.translated.name }} (Number: {{ order.orderNumber }}) on {{ order.orderDateTime|format_datetime('medium', 'short', locale='en-GB') }}  has changed.
            The new status is as follows: {{ order.stateMachineState.translated.name }}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
            However, in case you have purchased without a registration or a customer account, you do not have this option.

            EOF;
    }
}
