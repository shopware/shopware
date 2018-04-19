<?php

use Shopware\Framework\Migration\AbstractMigration;

class Migrations_Migration908 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up($modus)
    {
        if ($modus === AbstractMigration::MODUS_UPDATE) {
            return;
        }

        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr1` `attr1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr2` `attr2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr3` `attr3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr4` `attr4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr5` `attr5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr6` `attr6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr7` `attr7` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr8` `attr8` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr9` `attr9` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr10` `attr10` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr11` `attr11` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr12` `attr12` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr13` `attr13` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr14` `attr14` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr15` `attr15` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr16` `attr16` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr17` `attr17` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr18` `attr18` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr19` `attr19` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_attributes` CHANGE `attr20` `attr20` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_blog_attributes` CHANGE `attribute1` `attribute1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_blog_attributes` CHANGE `attribute2` `attribute2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_blog_attributes` CHANGE `attribute3` `attribute3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_blog_attributes` CHANGE `attribute4` `attribute4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_blog_attributes` CHANGE `attribute5` `attribute5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_blog_attributes` CHANGE `attribute6` `attribute6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_categories_attributes` CHANGE `attribute1` `attribute1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_categories_attributes` CHANGE `attribute2` `attribute2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_categories_attributes` CHANGE `attribute3` `attribute3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_categories_attributes` CHANGE `attribute4` `attribute4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_categories_attributes` CHANGE `attribute5` `attribute5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_categories_attributes` CHANGE `attribute6` `attribute6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_attributes` CHANGE `attribute1` `attribute1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_attributes` CHANGE `attribute2` `attribute2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_attributes` CHANGE `attribute3` `attribute3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_attributes` CHANGE `attribute4` `attribute4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_attributes` CHANGE `attribute5` `attribute5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_attributes` CHANGE `attribute6` `attribute6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_basket_attributes` CHANGE `attribute1` `attribute1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_basket_attributes` CHANGE `attribute2` `attribute2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_basket_attributes` CHANGE `attribute3` `attribute3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_basket_attributes` CHANGE `attribute4` `attribute4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_basket_attributes` CHANGE `attribute5` `attribute5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_basket_attributes` CHANGE `attribute6` `attribute6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_details_attributes` CHANGE `attribute1` `attribute1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_details_attributes` CHANGE `attribute2` `attribute2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_details_attributes` CHANGE `attribute3` `attribute3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_details_attributes` CHANGE `attribute4` `attribute4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_details_attributes` CHANGE `attribute5` `attribute5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_details_attributes` CHANGE `attribute6` `attribute6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_img_attributes` CHANGE `attribute1` `attribute1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_img_attributes` CHANGE `attribute2` `attribute2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_articles_img_attributes` CHANGE `attribute3` `attribute3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr1` `attr1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr2` `attr2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr3` `attr3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr4` `attr4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr5` `attr5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr6` `attr6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr7` `attr7` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr8` `attr8` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr9` `attr9` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr10` `attr10` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr11` `attr11` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr12` `attr12` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr13` `attr13` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr14` `attr14` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr15` `attr15` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr16` `attr16` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr17` `attr17` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr18` `attr18` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr19` `attr19` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_article_configurator_templates_attributes` CHANGE `attr20` `attr20` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_billingaddress_attributes` CHANGE `text1` `text1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_billingaddress_attributes` CHANGE `text2` `text2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_billingaddress_attributes` CHANGE `text3` `text3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_billingaddress_attributes` CHANGE `text4` `text4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_billingaddress_attributes` CHANGE `text5` `text5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_billingaddress_attributes` CHANGE `text6` `text6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_shippingaddress_attributes` CHANGE `text1` `text1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_shippingaddress_attributes` CHANGE `text2` `text2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_shippingaddress_attributes` CHANGE `text3` `text3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_shippingaddress_attributes` CHANGE `text4` `text4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_shippingaddress_attributes` CHANGE `text5` `text5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_order_shippingaddress_attributes` CHANGE `text6` `text6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_addresses_attributes` CHANGE `text1` `text1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_addresses_attributes` CHANGE `text2` `text2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_addresses_attributes` CHANGE `text3` `text3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_addresses_attributes` CHANGE `text4` `text4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_addresses_attributes` CHANGE `text5` `text5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_addresses_attributes` CHANGE `text6` `text6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_billingaddress_attributes` CHANGE `text1` `text1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_billingaddress_attributes` CHANGE `text2` `text2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_billingaddress_attributes` CHANGE `text3` `text3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_billingaddress_attributes` CHANGE `text4` `text4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_billingaddress_attributes` CHANGE `text5` `text5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_billingaddress_attributes` CHANGE `text6` `text6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_shippingaddress_attributes` CHANGE `text1` `text1` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_shippingaddress_attributes` CHANGE `text2` `text2` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_shippingaddress_attributes` CHANGE `text3` `text3` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_shippingaddress_attributes` CHANGE `text4` `text4` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_shippingaddress_attributes` CHANGE `text5` `text5` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
        $this->addSql("ALTER TABLE `s_user_shippingaddress_attributes` CHANGE `text6` `text6` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;");
    }
}
