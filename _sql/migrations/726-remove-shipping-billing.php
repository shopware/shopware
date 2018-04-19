<?php

class Migrations_Migration726 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus === self::MODUS_INSTALL) {
            return;
        }

        // move snippets to new namespace
        $moveSnippets = [
            'ConfirmAddressSelectButton',
            'ConfirmAddressSelectLink',
            'ConfirmHeaderBilling',
            'ConfirmHeaderPayment',
            'ConfirmHeaderPaymentShipping',
            'ConfirmHeaderShipping',
            'ConfirmInfoInstantDownload',
            'ConfirmInfoPaymentMethod',
            'ConfirmLinkChangePayment',
            'ConfirmSalutationMr',
            'ConfirmSalutationMs'
        ];

        $moveSnippets = join('","', $moveSnippets);
        $this->addSql('UPDATE s_core_snippets SET `namespace` = "frontend/checkout/confirm" WHERE `namespace` = "frontend/checkout/confirm_left" AND `name` IN ("'.$moveSnippets.'")');

        // delete orphan snippets
        $this->addSql('DELETE FROM `s_core_snippets` WHERE `namespace` = "frontend/account/select_address"');
        $this->addSql('DELETE FROM `s_core_snippets` WHERE `namespace` = "frontend/account/select_billing"');
        $this->addSql('DELETE FROM `s_core_snippets` WHERE `namespace` = "frontend/account/select_shipping"');
        $this->addSql('DELETE FROM `s_core_snippets` WHERE `namespace` = "frontend/account/confirm_left"');
    }
}
