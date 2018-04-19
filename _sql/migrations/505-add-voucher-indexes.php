<?php
class Migrations_Migration505 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('ALTER TABLE `s_emarketing_vouchers` ADD INDEX `modus` (`modus`)');
        $this->addSql('ALTER TABLE `s_emarketing_voucher_codes` ADD INDEX `voucherID_cashed` (`voucherID`, `cashed`)');
    }
}
