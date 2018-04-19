<?php
class Migrations_Migration701 extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        // remove old templates
        $this->addSql("DELETE FROM `s_core_templates` WHERE version < 3;");

        // remove unused table fields
        $this->addSql("ALTER TABLE `s_categories` DROP COLUMN `showfiltergroups`;");
        $this->addSql("ALTER TABLE `s_emotion` DROP COLUMN `container_width`;");

        // remove unused emotion options
        $this->addSql("DELETE FROM `s_library_component_field` WHERE `name` IN ('article_slider_numbers', 'manufacturer_slider_numbers', 'thumbnail_size');");

        // update outdated snippets
        $this->addSql("UPDATE `s_core_snippets` SET `value` = 'Bitte beachten Sie, dass keine Nummern sondern Punkte in der Desktop Ansicht angezeigt werden.' WHERE `name` = 'banner_slider_numbers/support' AND dirty = 0 AND localeID=1;");
        $this->addSql("UPDATE `s_core_snippets` SET `value` = 'Please note that there are only dots instead of numbers in the desktop viewport.' WHERE `name` = 'banner_slider_numbers/support' AND dirty = 0 AND localeID=2;");

        // remove unused config elements
        $optionsToDelete = [
            'maxsupplierscategory',
            'showbundlemainarticle',
            'paymentEditingInCheckoutPage',
            'basketHeaderColor',
            'basketHeaderFontColor',
            'basketTableColor',
            'fuzzysearchdistance',
            'fuzzysearchpricefilter',
            'fuzzysearchresultsperpage',
            'thumb'
        ];

        $optionsToDeleteSql = "'".implode("','", $optionsToDelete)."'";
        $sql = <<<SQL
DELETE elements, elementValues, elementTranslations
FROM `s_core_config_elements` as elements
LEFT JOIN `s_core_config_values` as elementValues
  ON elements.id = elementValues.element_id
LEFT JOIN `s_core_config_element_translations` as elementTranslations
  ON elements.id = elementTranslations.element_id
WHERE `name` IN ($optionsToDeleteSql)
SQL;

        $this->addSql($sql);


        // remove unused snippets
        $snippetsToDelete = ["article_slider_numbers","article_slider_numbers_support","manufacturer_slider_numbers/label","manufacturer_slider_numbers/support","thumbnail_size","thumbnail_size_support","LoginActionClose","LoginLabelNew","LoginActionCreateAccount","LoginLabelExisting","LoginTextExisting","LoginActionNext","ListingPaging","ListingTextPrevious","ListingTextNext","AccountLabelCurrentPassword","AccountLabelNewPassword","AccountLabelRepeatPassword","OrdersHeadline","PasswordLabelMail","BookmarkTwitter","BookmarkFacebook","BookmarkDelicious","BookmarkDiggit","BlogInfoRating","SlideArticleInfoContent","IndexActionShowPositions","sCartPremiumsHeadline","CartFooterSum","CartFooterShipping","CartFooterTotal","CheckoutFooterActionAddVoucher","CheckoutFooterIdLabelInline","CheckoutFooterActionAdd","CartColumnAvailability","sCartItemFree","CartItemInfoPremium","CartItemInfoBundle","ConfirmErrorAGB","ConfirmHeadlinePersonalInformation","CheckoutFooterLabelAddVoucher","CheckoutFooterLabelAddArticle","ConfirmLabelComment","CheckoutDispatchLinkSend","CheckoutItemPrice","CheckoutItemLaststock","CheckoutPaymentHeadline","FinishHeaderItems","sBonusPriceFree","ShippingHeader","DispatchHeadNotice","CompareActionClose","CompareHeader","BundleHeader","BundleInfoPriceForAll","BundleActionAdd","BundleInfoPriceInstead","BundleInfoPercent","LiveTickerStartPrice","LiveTickerCurrentPrice","LiveTimeDays","LiveTimeHours","LiveTimeMinutes","LiveTimeSeconds","LiveTimeRemainingPieces","LiveTimeRemaining","LiveCategoryPreviousPrice","LiveCategorySavingPercent","LiveCategoryOffersEnds","LiveCategoryCurrentPrice","LiveCountdownStartPrice","LiveCountdownCurrentPrice","LiveCountdownPriceFails","LiveCountdownPriceRising","LiveCountdownMinutes","LiveCountdownRemaining","LiveCountdownRemainingPieces","DetailFrom","DetailDataHeaderBlockprices","DetailBuyValueSelect","DetailBuyLabelQuantity","DetailCommentInfoAverageRate","DetailCommentInfoRating","DetailCommentLabelRating","DetailCommentLabelText","DetailDescriptionSupplier","DetailChooseFirst","DetailFromNew","DetailSimilarHeader","FormsLinkBack","FormsTextContact","IndexMetaShortcutIcon","IndexMetaMsNavButtonColor","IndexRealizedWith","IndexRealizedShopsystem","MenuLeftHeading","IndexSearchFieldValue","ListingBoxInstantDownload","ListingBoxLinkBuy","SimilarBoxLinkDetails","SimilarBoxMore","FilterSupplierHeadline","ListingLinkAllSuppliers","ListingActionsSettingsTitle","ListingActionsSettingsTable","ListingActionsSettingsList","NewsletterDetailLinkNewWindow","NewsletterLabelSelect","sNewsletterLabelMail","NewsletterRegisterLabelSalutation","NewsletterRegisterPleaseChoose","NewsletterRegisterLabelFirstname","NewsletterRegisterLabelLastname","NewsletterRegisterBillingLabelStreet","NewsletterRegisterBillingLabelCity","NoteColumnName","NoteColumnPrice","NoteLinkBuy","NoteLinkDetails","PalpalPendingTitle","PalpalPendingInfo","PalpalPendingLinkHomepage","TagcloudHead","DetailNotifyActionSubmit","RegisterLabelCompany","RegisterBillingLabelStreet","RegisterBillingLabelCity","RegisterBillingLabelCountry","RegisterBillingLabelSelect","RegisterErrorHeadline","RegisterInfoSupplier","RegisterInfoSupplier2","RegisterIndexActionSubmit","RegisterInfoAdvantages","RegisterPersonalHeadline","RegisterLabelSalutation","RegisterLabelFirstname","RegisterLabelLastname","RegisterLabelMailConfirmation","RegisterLabelPassword","RegisterLabelPasswordRepeat","RegisterLabelPhone","RegisterLabelBirthday","RegisterShippingLabelSalutation","RegisterShippingLabelCompany","RegisterShippingLabelDepartment","RegisterShippingLabelFirstname","RegisterShippingLabelLastname","RegisterShippingLabelStreet","RegisterShippingLabelCity","RegisterShippingLabelCountry","RegisterShippingLabelSelect","CheckoutStepBasketNumber","CheckoutStepBasketText","CheckoutStepRegisterNumber","CheckoutStepRegisterText","SearchFilterCategoryHeading","SearchFuzzyHeadlineEmpty","SearchLeftHeadlineCutdown","SearchLeftHeadlineFilter","SearchLeftLinkAllFilters","SearchLeftLinkDefault","SearchLeftHeadlineSupplier","SearchLeftInfoSuppliers","SearchLeftLinkAllSuppliers","SearchLeftHeadlinePrice","SearchLeftLinkAllPrices","SearchTo","SearchWere","SearchArticlesFound","SitemapHeader","TellAFriendLabelMail","TellAFriendLabelComment","TellAFriendLabelCaptcha","DetailBoughtArticlesSlider","DetailViewedArticlesSlider"];
        $snippetsToDeleteSql = "'".implode("','", $snippetsToDelete)."'";
        $sql = <<<SQL
DELETE snippet
FROM `s_core_snippets` as snippet
WHERE
  `name` IN ($snippetsToDeleteSql) AND
  `dirty` = 0
SQL;

        $this->addSql($sql);
    }
}
