<?php
class Migrations_Migration206 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
        ALTER TABLE  `s_articles_img_attributes` CHANGE  `attribute1`  `attribute1` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL ,
        CHANGE  `attribute2`  `attribute2` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL ,
        CHANGE  `attribute3`  `attribute3` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL ;
EOD;
        $this->addSql($sql);
    }
}
