---
title: Prepare several methods to be native typed
issue: NEXT-14973
---
# Core
* Deprecated the following methods. They will all have a native typehint for their parameters and/or return type with Shopware 6.5.0.0
  * Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer::iterate
  * Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer::iterate
  * Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer::iterate
  * Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer::iterate
  * Shopware\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer::iterate
  * Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderConfigurationIndexer::iterate
  * Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer::iterate
  * Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexer::iterate
  * Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer::iterate
  * Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater::iterate
  * Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer::iterate
  * Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexer::iterate
  * Shopware\Core\System\SalesChannel\DataAbstractionLayer\SalesChannelIndexer::iterate

  * Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent::addData
  * Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent::addTemplateData

  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\BoolFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionDataPayloadFieldSerializer::decode
  * Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer::decode

  * Shopware\Core\Kernel::registerBundles
  * Shopware\Core\Kernel::getProjectDir
