<?php

class Migrations_Migration811 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = 'CREATE TABLE `s_emarketing_partner_attributes` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`partnerID` INT(11) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `partnerID` (`partnerID`),
	CONSTRAINT `FK__s_emarketing_partner` FOREIGN KEY (`partnerID`) REFERENCES `s_emarketing_partner` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE
)
ENGINE=InnoDB
;';
        $this->addSql($sql);
    }
}
