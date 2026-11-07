<?php declare(strict_types=1);

// Existing complexity and architecture violations, see CoreLayerRule and the cognitive_complexity limits in phpstan.neon.dist.
// This file may only shrink. New code must follow the rules.

$ignoreErrors = [];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Administration\\Migration\\V6_4\\Migration1636121186CopySalesChannelIdsIntoUserConfig should not depend on Shopware\\Core\\System\\Language\\LanguageDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Administration/Migration/V6_4/Migration1636121186CopySalesChannelIdsIntoUserConfig.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Administration\\Migration\\V6_4\\Migration1636121186CopySalesChannelIdsIntoUserConfig should not depend on Shopware\\Core\\System\\SalesChannel\\Aggregate\\SalesChannelTranslation\\SalesChannelTranslationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Administration/Migration/V6_4/Migration1636121186CopySalesChannelIdsIntoUserConfig.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Administration\\Migration\\V6_4\\Migration1636121186CopySalesChannelIdsIntoUserConfig should not depend on Shopware\\Core\\System\\User\\UserDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Administration/Migration/V6_4/Migration1636121186CopySalesChannelIdsIntoUserConfig.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Cart\\Address\\AddressValidator::validate()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Cart/Address/AddressValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Cart\\Delivery\\DeliveryBuilder::buildPositions()" is 27, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Cart/Delivery/DeliveryBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Cart\\LineItem\\Group\\LineItemGroupBuilder::findGroupPackages()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Cart/LineItem/Group/LineItemGroupBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 95, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Cart/Order/OrderConverter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Cart\\Order\\OrderConverter::assembleSalesChannelContext()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Cart/Order/OrderConverter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Cart\\Order\\OrderConverter::convertDeliveries()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Cart/Order/OrderConverter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Cart\\Order\\OrderConverter::convertToOrder()" is 40, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Cart/Order/OrderConverter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Customer\\SalesChannel\\RegisterRoute::_register()" is 29, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Customer/SalesChannel/RegisterRoute.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Customer\\Validation\\Constraint\\CustomerVatIdentification::__construct()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Customer/Validation/Constraint/CustomerVatIdentification.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Customer\\Validation\\CustomerEmailUniqueChecker::findConflictingChecks()" is 31, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Customer/Validation/CustomerEmailUniqueChecker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Document\\DocumentConfigurationFactory::mergeConfiguration()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Document/DocumentConfigurationFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Document\\Renderer\\CreditNoteRenderer::render()" is 29, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Document/Renderer/CreditNoteRenderer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Document\\Renderer\\DeliveryNoteRenderer::render()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Document/Renderer/DeliveryNoteRenderer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Document\\Renderer\\InvoiceRenderer::render()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Document/Renderer/InvoiceRenderer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Document\\Renderer\\StornoRenderer::render()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Document/Renderer/StornoRenderer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Document\\Renderer\\ZugferdCreditNoteRenderer::render()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Document/Renderer/ZugferdCreditNoteRenderer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Document\\Zugferd\\ZugferdDocument::withDelivery()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Document/Zugferd/ZugferdDocument.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\DocumentV2\\Generation\\DocumentArchiveGenerator::writeDocumentFiles()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/DocumentV2/Generation/DocumentArchiveGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\DocumentV2\\Generation\\DocumentDependencyResolver::sortFormats()" is 28, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/DocumentV2/Generation/DocumentDependencyResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Order\\SalesChannel\\OrderRoute::_load()" is 34, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Order/SalesChannel/OrderRoute.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Order\\SalesChannel\\SetPaymentOrderRoute::tryTransition()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Order/SalesChannel/SetPaymentOrderRoute.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Payment\\PaymentProcessor::finalize()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Payment/PaymentProcessor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Payment\\PaymentProcessor::pay()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Payment/PaymentProcessor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 83, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionCalculator::calculate()" is 37, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionCalculator::calculateDiscount()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionCalculator::isExcluded()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 88, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionCollector.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionCollector::collect()" is 57, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionCollector.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 95, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionDeliveryCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionDeliveryCalculator::calculate()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionDeliveryCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionDeliveryCalculator::isExcluded()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionDeliveryCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionDeliveryCalculator::reduceDiscountLineItemsIfFixedPresent()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionDeliveryCalculator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionProcessor::process()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionProcessor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionRedemptionLocker::acquireLocks()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Cart/PromotionRedemptionLocker.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\DataAbstractionLayer\\PromotionExclusionUpdater::update()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/DataAbstractionLayer/PromotionExclusionUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\PromotionEntity::getPreconditionRule()" is 31, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/PromotionEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Checkout\\Promotion\\Validator\\PromotionValidator::validatePromotion()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Checkout/Promotion/Validator/PromotionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Category\\CategoryHydrator::assign()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Category/CategoryHydrator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Category\\DataAbstractionLayer\\CategoryIndexer::update()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Category/DataAbstractionLayer/CategoryIndexer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Category\\Service\\CategoryBreadcrumbBuilder::convertCategoriesToBreadcrumbUrls()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Category/Service/CategoryBreadcrumbBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Category\\Subscriber\\CategorySubscriber::beforeWriteCategory()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Category/Subscriber/CategorySubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Cms\\DataResolver\\Element\\AbstractCmsElementResolver::resolveEntityValue()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Cms/DataResolver/Element/AbstractCmsElementResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Cms\\SalesChannel\\SalesChannelCmsPageLoader::extractProductIds()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Cms/SalesChannel/SalesChannelCmsPageLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Cms\\SalesChannel\\SalesChannelCmsPageLoader::load()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Cms/SalesChannel/SalesChannelCmsPageLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 88, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Cookie/Service/CookieProvider.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Cookie\\Service\\CookieProvider::convertLegacyCookies()" is 71, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Cookie/Service/CookieProvider.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetCustomerCustomFieldAction::getCustomFieldForUpdating()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Flow/Dispatching/Action/SetCustomerCustomFieldAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetCustomerGroupCustomFieldAction::getCustomFieldForUpdating()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Flow/Dispatching/Action/SetCustomerGroupCustomFieldAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetOrderCustomFieldAction::getCustomFieldForUpdating()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Flow/Dispatching/Action/SetOrderCustomFieldAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\DataAbstractionLayer\\Serializer\\Entity\\EntitySerializer::deserialize()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/EntitySerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\DataAbstractionLayer\\Serializer\\Entity\\MediaSerializer::deserialize()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/MediaSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\DataAbstractionLayer\\Serializer\\Entity\\OrderSerializer::serialize()" is 27, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/OrderSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\DataAbstractionLayer\\Serializer\\Entity\\ProductSerializer::deserialize()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/ProductSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\DataAbstractionLayer\\Serializer\\Entity\\ProductSerializer::getMediaUrls()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/ProductSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 81, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Field/FieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\DataAbstractionLayer\\Serializer\\Field\\FieldSerializer::deserialize()" is 32, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Field/FieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\DataAbstractionLayer\\Serializer\\Field\\FieldSerializer::serialize()" is 46, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Field/FieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\Event\\Subscriber\\CustomerNumberRangeSubscriber::onAfterImport()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/Event/Subscriber/CustomerNumberRangeSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\Event\\Subscriber\\ProductCategoryPathsSubscriber::categoryPathsToAssignment()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/Event/Subscriber/ProductCategoryPathsSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 130, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/ImportExport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\ImportExport::export()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/ImportExport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\ImportExport::exportChunk()" is 28, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/ImportExport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\ImportExport::import()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/ImportExport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\ImportExport::logResults()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/ImportExport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\Message\\ImportExportHandler::__invoke()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/Message/ImportExportHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ImportExport\\Service\\MappingService::guessKeyFromMappedKey()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ImportExport/Service/MappingService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Mail\\Service\\MailAttachmentsBuilder::mapDocumentFilesByFormats()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Mail/Service/MailAttachmentsBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Mail\\Service\\MailFactory::create()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Mail/Service/MailFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Mail\\Service\\MailService::createMail()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Mail/Service/MailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Mail\\Service\\MailService::send()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Mail/Service/MailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\MailTemplate\\Service\\MailDataSimulator::generateEntityData()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/MailTemplate/Service/MailDataSimulator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\MailTemplate\\Service\\MailTemplateService::getAvailableVariables()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/MailTemplate/Service/MailTemplateService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Cms\\ImageCmsElementResolver::addMediaEntity()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Cms/ImageCmsElementResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Cms\\Type\\ImageSliderTypeDataResolver::enrich()" is 33, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Cms/Type/ImageSliderTypeDataResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Cms\\VideoCmsElementResolver::addMediaEntity()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Cms/VideoCmsElementResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Cms\\YoutubeVideoCmsElementResolver::addMediaEntity()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Cms/YoutubeVideoCmsElementResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Core\\Application\\MediaUrlLoader::map()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Core/Application/MediaUrlLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\File\\FileSaver::doRenameMedia()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/File/FileSaver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\File\\SvgContentValidator::validateDocument()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/File/SvgContentValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\File\\TrustedUrlResolver::__construct()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/File/TrustedUrlResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\MediaHydrator::assign()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/MediaHydrator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 95, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Thumbnail/ThumbnailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Thumbnail\\ThumbnailService::generate()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Thumbnail/ThumbnailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Thumbnail\\ThumbnailService::generateAndSave()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Thumbnail/ThumbnailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Media\\Thumbnail\\ThumbnailService::updateThumbnails()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Media/Thumbnail/ThumbnailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 105, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/Cart/ProductCartProcessor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\Cart\\ProductCartProcessor::collect()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/Cart/ProductCartProcessor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\Cart\\ProductCartProcessor::getNotCompleted()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/Cart/ProductCartProcessor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 127, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/Cart/ProductFeatureBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\Cart\\ProductLineItemCommandValidator::preValidate()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/Cart/ProductLineItemCommandValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\CheapestPrice\\CheapestPriceContainer::resolve()" is 29, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/DataAbstractionLayer/CheapestPrice/CheapestPriceContainer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\CheapestPriceUpdater::update()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/DataAbstractionLayer/CheapestPriceUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\Garan\\GaranLabelMailSubscriber::addLabels()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/Garan/GaranLabelMailSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\ProductHydrator::assign()" is 59, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/ProductHydrator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\SalesChannel\\Detail\\ProductConfiguratorLoader::sortSettings()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/SalesChannel/Detail/ProductConfiguratorLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\SalesChannel\\Listing\\ProductListingLoader::loadPreviews()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/SalesChannel/Listing/ProductListingLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\SearchKeyword\\ProductSearchKeywordAnalyzer::resolveEntityValue()" is 27, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/SearchKeyword/ProductSearchKeywordAnalyzer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\SearchKeyword\\ProductSearchTermInterpreter::slop()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/SearchKeyword/ProductSearchTermInterpreter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Product\\Subscriber\\ProductSubscriber::beforeWriteProduct()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Product/Subscriber/ProductSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ProductExport\\ProductExportHydrator::assign()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ProductExport/ProductExportHydrator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ProductExport\\Service\\ProductExportGenerator::generate()" is 44, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ProductExport/Service/ProductExportGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ProductExport\\Validator\\GoogleProductExportValidator::validateProviderExport()" is 48, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ProductExport/Validator/GoogleProductExportValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\ProductStream\\DataAbstractionLayer\\ProductStreamIndexer::buildNested()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/ProductStream/DataAbstractionLayer/ProductStreamIndexer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Rule\\DataAbstractionLayer\\RuleAreaUpdater::update()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Rule/DataAbstractionLayer/RuleAreaUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Rule\\DataAbstractionLayer\\RulePayloadUpdater::buildNested()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Rule/DataAbstractionLayer/RulePayloadUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Seo\\SalesChannel\\StoreApiSeoResolver::enrich()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Seo/SalesChannel/StoreApiSeoResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Seo\\SalesChannel\\StoreApiSeoResolver::findStruct()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Seo/SalesChannel/StoreApiSeoResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Seo\\SeoResolver::resolveUrl()" is 33, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Seo/SeoResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Seo\\SeoUrlPersister::doUpdateSeoUrls()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Seo/SeoUrlPersister.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Content\\Sitemap\\Service\\SitemapExporter::initSitemapHandles()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Content/Sitemap/Service/SitemapExporter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\AbstractClassUsageRule::processNode()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/AbstractClassUsageRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\AclValidPermissionsInMethodRule::processNode()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/AclValidPermissionsInMethodRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\AclValidPermissionsInRouteAttributesRule::validateAttribute()" is 48, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/AclValidPermissionsInRouteAttributesRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\CodeCoverageIgnore\\LogicDetector::rewritesALocal()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/CodeCoverageIgnore/LogicDetector.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\CodeCoverageIgnoreEvaluationRule::redefinesAParentDefault()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/CodeCoverageIgnoreEvaluationRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\DecorationPatternRule::processNode()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/DecorationPatternRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 218, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/BCChangeAttributeUsageRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\BCChangeAttributeUsageRule::processNode()" is 52, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/BCChangeAttributeUsageRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\BCChangeAttributeUsageRule::validateClassHierarchyChange()" is 27, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/BCChangeAttributeUsageRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\BCChangeAttributeUsageRule::validateExceptionChange()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/BCChangeAttributeUsageRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\BCChangeAttributeUsageRule::validateMethodLevel()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/BCChangeAttributeUsageRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\BCChangeAttributeUsageRule::validatePropertyLevel()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/BCChangeAttributeUsageRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\DeprecatedExceptionPattern::check()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/DeprecatedExceptionPattern.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\DeprecatedFrameworkMethodPattern::check()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/DeprecatedFrameworkMethodPattern.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\DeprecatedMethodsThrowDeprecationRule::processNode()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/DeprecatedMethodsThrowDeprecationRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\DeprecatedServiceDefinitionFeatureTagRule::processNode()" is 48, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/DeprecatedServiceDefinitionFeatureTagRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\DeprecatedServiceFeatureTagRule::processNode()" is 31, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/DeprecatedServiceFeatureTagRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Deprecation\\FuturePropertyCompatibilityRule::processNode()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Deprecation/FuturePropertyCompatibilityRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\FeatureFlagVersionRule::processNode()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/FeatureFlagVersionRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Internal\\InternalClassRule::processNode()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Internal/InternalClassRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Migration\\AddColumnRule::processNode()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Migration/AddColumnRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\NoDALAutoload::processNode()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/NoDALAutoload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\NoNativeTimeFunctionRule::checkStrtotime()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/NoNativeTimeFunctionRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\NoUpdatesInExecuteQueryRule::processNode()" is 41, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/NoUpdatesInExecuteQueryRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\PropertyNativeTypeRule::processNode()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/PropertyNativeTypeRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\RouteScopeRule::processNode()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/RouteScopeRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 114, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/TaggedServiceContractRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\TaggedServiceContractRule::getLocatorTagsByServiceId()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/TaggedServiceContractRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\TaggedServiceContractRule::getTaggedArgumentsByServiceId()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/TaggedServiceContractRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\DataProviderRowArityRule::processNode()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/DataProviderRowArityRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoAssertionInEventHookRule::processNode()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoAssertionInEventHookRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 506, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::findInlineStubMocks()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::findPropertyMockIssues()" is 50, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::findReturnedVariableNames()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::helperResultIsNeverExpected()" is 73, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::helperUsageOfPropertyIsUnsafe()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::linkParentCalls()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::processNode()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::referenceIsNeverExpected()" is 75, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoCreateMockWithoutExpectationsRule::returnedCreateMocks()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoCreateMockWithoutExpectationsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoDependsWithDataProviderRule::processNode()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoDependsWithDataProviderRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\StaticAnalyze\\PHPStan\\Rules\\Tests\\NoReflectionInUnitTestsRule::processNode()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/StaticAnalyze/PHPStan/Rules/Tests/NoReflectionInUnitTestsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\System\\Command\\SyncComposerVersionCommand::execute()" is 39, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/System/Command/SyncComposerVersionCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\DevOps\\Test\\Command\\MakeCoverageTestCommand::filterExcludedClasses()" is 28, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/DevOps/Test/Command/MakeCoverageTestCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Checkout\\Cart\\CachedRuleLoader',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerGroup\\CustomerGroupDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Checkout\\Payment\\SalesChannel\\PaymentMethodRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Checkout\\Shipping\\SalesChannel\\ShippingMethodRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Category\\Aggregate\\CategoryTranslation\\CategoryTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Category\\CategoryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Category\\Event\\CategoryIndexerEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Category\\SalesChannel\\NavigationRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Cms\\CmsPageDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\LandingPage\\Event\\LandingPageIndexerEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Media\\Event\\MediaIndexerEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\ProductStream\\DataAbstractionLayer\\ProductStreamWriteResultHelper',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\ProductStream\\ProductStreamDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCrossSellingAssignedProducts\\ProductCrossSellingAssignedProductsDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCrossSellingTranslation\\ProductCrossSellingTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCrossSelling\\ProductCrossSellingDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductManufacturer\\ProductManufacturerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductProperty\\ProductPropertyDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Product\\Events\\InvalidateProductCache',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Sitemap\\Event\\SitemapGeneratedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheInvalidationSubscriber should not depend on Shopware\\Core\\Content\\Sitemap\\SalesChannel\\SitemapRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheInvalidationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheStateSubscriber should not depend on Shopware\\Core\\Checkout\\Cart\\Event\\CartChangedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheStateSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheStateSubscriber should not depend on Shopware\\Core\\Checkout\\Cart\\SalesChannel\\CartService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheStateSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheStateSubscriber should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLoginEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/CacheStateSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\Http\\CacheHeadersService should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/Http/CacheHeadersService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Adapter\\Cache\\Http\\CachePolicyProvider::getPolicy()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/Http/CachePolicyProvider.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Adapter\\Cache\\Http\\CacheResponseSubscriber::setResponseCache()" is 41, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/Http/CacheResponseSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\Http\\CacheResponseSubscriber should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/Http/CacheResponseSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\Http\\CacheResponseSubscriber should not depend on Shopware\\Core\\Checkout\\Cart\\SalesChannel\\CartService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/Http/CacheResponseSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Adapter\\Cache\\Http\\CacheStore::lookup()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/Http/CacheStore.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\Http\\Extension\\CacheHashRequiredExtension should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Cache/Http/Extension/CacheHashRequiredExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Adapter\\Database\\MySQLFactory::create()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Database/MySQLFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Adapter\\Filesystem\\Adapter\\AsyncAwsS3WriteBatchAdapter::writeBatch()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Filesystem/Adapter/AsyncAwsS3WriteBatchAdapter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\BuildBreadcrumbExtension should not depend on Shopware\\Core\\Content\\Category\\CategoryCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/BuildBreadcrumbExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\BuildBreadcrumbExtension should not depend on Shopware\\Core\\Content\\Category\\CategoryEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/BuildBreadcrumbExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\BuildBreadcrumbExtension should not depend on Shopware\\Core\\Content\\Category\\SalesChannel\\SalesChannelCategoryEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/BuildBreadcrumbExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\BuildBreadcrumbExtension should not depend on Shopware\\Core\\Content\\Category\\Service\\CategoryBreadcrumbBuilder',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/BuildBreadcrumbExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\CategoryUrlExtension should not depend on Shopware\\Core\\Content\\Category\\CategoryEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/CategoryUrlExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\CategoryUrlExtension should not depend on Shopware\\Core\\Content\\Category\\SalesChannel\\SalesChannelCategoryEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/CategoryUrlExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\CategoryUrlExtension should not depend on Shopware\\Core\\Content\\Category\\Service\\AbstractCategoryUrlGenerator',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/CategoryUrlExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\EntitySeoUrlFunctionExtension should not depend on Shopware\\Core\\Content\\Seo\\SeoUrlRoute\\EntityRouteResolver',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/EntitySeoUrlFunctionExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\MediaExtension should not depend on Shopware\\Core\\Content\\Media\\MediaCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/MediaExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\SeoUrlFunctionExtension should not depend on Shopware\\Core\\Content\\Seo\\SeoUrlPlaceholderHandlerInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/Extension/SeoUrlFunctionExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Adapter\\Twig\\TemplateFinder::resolve()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/TemplateFinder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Adapter\\Twig\\TwigVariableParser::getVariables()" is 39, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Adapter/Twig/TwigVariableParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Acl\\AclCriteriaValidator::validate()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Acl/AclCriteriaValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\EntitySchemaGenerator::mapFieldType()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/EntitySchemaGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 152, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/OpenApi/OpenApiDefinitionSchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\OpenApi\\OpenApiDefinitionSchemaBuilder::getExtensions()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/OpenApi/OpenApiDefinitionSchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\OpenApi\\OpenApiDefinitionSchemaBuilder::getSchemaByDefinition()" is 74, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/OpenApi/OpenApiDefinitionSchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\OpenApi\\OpenApiDefinitionSchemaBuilder::resolveJsonField()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/OpenApi/OpenApiDefinitionSchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\OpenApi\\OpenApiDefinitionSchemaBuilder should not depend on Shopware\\Core\\Content\\MeasurementSystem\\Field\\MeasurementUnitsField',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/OpenApi/OpenApiDefinitionSchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\OpenApi3Generator::getSchema()" is 35, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/OpenApi3Generator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 197, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/StoreApiGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\StoreApiGenerator::enrichPathsWithAssociations()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/StoreApiGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\StoreApiGenerator::extractEntityNameFromOperation()" is 37, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/StoreApiGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\StoreApiGenerator::injectHeaderParameter()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/StoreApiGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\ApiDefinition\\Generator\\StoreApiGenerator::resolveParameterGroups()" is 29, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/ApiDefinition/Generator/StoreApiGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Command\\DumpClassSchemaCommand::dumpProperties()" is 48, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Command/DumpClassSchemaCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 121, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/ApiController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Controller\\ApiController::buildEntityPath()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/ApiController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Controller\\ApiController::resolveSearch()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/ApiController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Controller\\ApiController::write()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/ApiController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\IndexingController should not depend on Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\ProductIndexingMessage',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/IndexingController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\InfoController should not depend on Shopware\\Core\\Content\\Flow\\Api\\FlowActionCollector',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/InfoController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\InfoController should not depend on Shopware\\Core\\Content\\Media\\Upload\\MediaFileExtensionListProvider',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/InfoController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\InfoController should not depend on Shopware\\Core\\Content\\Media\\Upload\\PresignedMediaUploadService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/InfoController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Cart\\ApiOrderCartService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CalculatedPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Cart\\Processor',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Cart\\SalesChannel\\AbstractCartOrderRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Cart\\SalesChannel\\CartService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTaxCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRuleCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\CheckoutPermissions',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Customer\\ImitateCustomerTokenGenerator',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Checkout\\Customer\\Struct\\ImitateCustomerToken',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SendMailAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController should not depend on Shopware\\Core\\Content\\MailTemplate\\Subscriber\\MailSendSubscriberConfig',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Controller/SalesChannelProxyController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\EventListener\\Acl\\CreditOrderLineItemListener should not depend on Shopware\\Core\\Checkout\\Cart\\LineItem\\LineItem',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/EventListener/Acl/CreditOrderLineItemListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Api\\EventListener\\Acl\\CreditOrderLineItemListener should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderLineItem\\OrderLineItemDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/EventListener/Acl/CreditOrderLineItemListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\EventListener\\ErrorResponseFactory::convert()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/EventListener/ErrorResponseFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\EventListener\\ExpectationSubscriber::checkPackages()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/EventListener/ExpectationSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Serializer\\JsonApiEncoder::addExtensions()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/JsonApiEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Serializer\\JsonApiEncoder::createSerializedEntity()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/JsonApiEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Serializer\\JsonEntityEncoder::filterDecodedExtensions()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/JsonEntityEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Serializer\\JsonEntityEncoder::filterDecodedFields()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/JsonEntityEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Serializer\\JsonEntityEncoder::removeNotAllowedFields()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/JsonEntityEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Serializer\\Record::merge()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Serializer/Record.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Api\\Sync\\SyncFkResolver::collect()" is 38, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Api/Sync/SyncFkResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppPaymentMethod\\AppPaymentMethodDefinition should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppPaymentMethod/AppPaymentMethodDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppPaymentMethod\\AppPaymentMethodDefinition should not depend on Shopware\\Core\\Content\\Media\\MediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppPaymentMethod/AppPaymentMethodDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppPaymentMethod\\AppPaymentMethodEntity should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppPaymentMethod/AppPaymentMethodEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppPaymentMethod\\AppPaymentMethodEntity should not depend on Shopware\\Core\\Content\\Media\\MediaEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppPaymentMethod/AppPaymentMethodEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppScriptCondition\\AppScriptConditionDefinition should not depend on Shopware\\Core\\Content\\Rule\\Aggregate\\RuleCondition\\RuleConditionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppScriptCondition/AppScriptConditionDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppScriptCondition\\AppScriptConditionEntity should not depend on Shopware\\Core\\Content\\Rule\\Aggregate\\RuleCondition\\RuleConditionCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppScriptCondition/AppScriptConditionEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppShippingMethod\\AppShippingMethodDefinition should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppShippingMethod/AppShippingMethodDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppShippingMethod\\AppShippingMethodDefinition should not depend on Shopware\\Core\\Content\\Media\\MediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppShippingMethod/AppShippingMethodDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppShippingMethod\\AppShippingMethodEntity should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppShippingMethod/AppShippingMethodEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\AppShippingMethod\\AppShippingMethodEntity should not depend on Shopware\\Core\\Content\\Media\\MediaEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/AppShippingMethod/AppShippingMethodEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\FlowAction\\AppFlowActionDefinition should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowSequence\\FlowSequenceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/FlowAction/AppFlowActionDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\FlowAction\\AppFlowActionEntity should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowSequence\\FlowSequenceCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/FlowAction/AppFlowActionEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\FlowEvent\\AppFlowEventDefinition should not depend on Shopware\\Core\\Content\\Flow\\FlowDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/FlowEvent/AppFlowEventDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Aggregate\\FlowEvent\\AppFlowEventEntity should not depend on Shopware\\Core\\Content\\Flow\\FlowCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Aggregate/FlowEvent/AppFlowEventEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\CheckoutGatewayException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 6,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\CheckoutGatewayInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\CheckoutGatewayResponse',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\Command\\AbstractCheckoutGatewayCommand',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\Command\\CheckoutGatewayCommandCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\Command\\Event\\CheckoutGatewayCommandsCollectedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\Command\\Executor\\CheckoutGatewayCommandExecutor',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\Command\\Registry\\CheckoutGatewayCommandRegistry',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Gateway\\Command\\Struct\\CheckoutGatewayPayloadStruct',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Gateway\\AppCheckoutGateway should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Gateway/AppCheckoutGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Checkout\\Payload\\AppCheckoutGatewayPayload should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Checkout/Payload/AppCheckoutGatewayPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\App\\Cookie\\AppCookieCollectListener::addCookies()" is 35, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Cookie/AppCookieCollectListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Cookie\\AppCookieCollectListener should not depend on Shopware\\Core\\Content\\Cookie\\Event\\CookieGroupCollectEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Cookie/AppCookieCollectListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Cookie\\AppCookieCollectListener should not depend on Shopware\\Core\\Content\\Cookie\\Struct\\CookieEntry',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Cookie/AppCookieCollectListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Cookie\\AppCookieCollectListener should not depend on Shopware\\Core\\Content\\Cookie\\Struct\\CookieEntryCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Cookie/AppCookieCollectListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Cookie\\AppCookieCollectListener should not depend on Shopware\\Core\\Content\\Cookie\\Struct\\CookieGroup',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Cookie/AppCookieCollectListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Cookie\\AppCookieCollectListener should not depend on Shopware\\Core\\Content\\Cookie\\Struct\\CookieGroupCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Cookie/AppCookieCollectListener.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Event\\CustomAppEvent should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Aware\\CustomAppAware',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Event/CustomAppEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Flow\\Action\\AppFlowActionProvider should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\StorableFlow',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Flow/Action/AppFlowActionProvider.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\App\\Http\\AppSystemHttpMiddleware::__invoke()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Http/AppSystemHttpMiddleware.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Http\\AppSystemHttpMiddleware should not depend on Shopware\\Core\\Content\\Media\\File\\TrustedUrlResolver',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Http/AppSystemHttpMiddleware.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Http\\AppSystemHttpMiddleware should not depend on Shopware\\Core\\Content\\Media\\MediaException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Http/AppSystemHttpMiddleware.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\PaymentMethodLifecycleHandler::persist()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/PaymentMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\PaymentMethodLifecycleHandler should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/PaymentMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\PaymentMethodLifecycleHandler should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/PaymentMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\PaymentMethodLifecycleHandler should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/PaymentMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\PaymentMethodLifecycleHandler should not depend on Shopware\\Core\\Content\\Media\\MediaCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/PaymentMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\PaymentMethodLifecycleHandler should not depend on Shopware\\Core\\Content\\Media\\MediaService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/PaymentMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\RuleConditionLifecycleHandler::hydrateConstraints()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/RuleConditionLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\ShippingMethodLifecycleHandler should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/ShippingMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\ShippingMethodLifecycleHandler should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/ShippingMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\ShippingMethodLifecycleHandler should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/ShippingMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\ShippingMethodLifecycleHandler should not depend on Shopware\\Core\\Content\\Media\\MediaCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/ShippingMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Lifecycle\\Handler\\ShippingMethodLifecycleHandler should not depend on Shopware\\Core\\Content\\Media\\MediaService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Lifecycle/Handler/ShippingMethodLifecycleHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\App\\Manifest\\Manifest::create()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Manifest/Manifest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Manifest\\Xml\\ShippingMethod\\ShippingMethod should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Manifest/Xml/ShippingMethod/ShippingMethod.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransactionCaptureRefund\\OrderTransactionCaptureRefundCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransactionCaptureRefund\\OrderTransactionCaptureRefundDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransactionCaptureRefund\\OrderTransactionCaptureRefundEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Order\\OrderEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\AbstractPaymentHandler',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\PaymentHandlerType',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 5,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentTransactionStruct',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Payment\\Cart\\Recurring\\RecurringDataStruct',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Payment\\Cart\\RefundPaymentTransactionStruct',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Handler\\AppPaymentHandler should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 5,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Handler/AppPaymentHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\PaymentPayload should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 5,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/PaymentPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\PaymentPayload should not depend on Shopware\\Core\\Checkout\\Order\\OrderEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/PaymentPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\PaymentPayload should not depend on Shopware\\Core\\Checkout\\Payment\\Cart\\Recurring\\RecurringDataStruct',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/PaymentPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\PaymentPayloadInterface should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/PaymentPayloadInterface.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\RefundPayload should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransactionCaptureRefund\\OrderTransactionCaptureRefundEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/RefundPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\RefundPayload should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/RefundPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\RefundPayload should not depend on Shopware\\Core\\Checkout\\Order\\OrderEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/RefundPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\RefundPayload should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/RefundPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\ValidatePayload should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/ValidatePayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\Payment\\Payload\\Struct\\ValidatePayload should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/Payment/Payload/Struct/ValidatePayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\TaxProvider\\Payload\\TaxProviderPayload should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/TaxProvider/Payload/TaxProviderPayload.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\TaxProvider\\Payload\\TaxProviderPayloadService should not depend on Shopware\\Core\\Checkout\\Cart\\TaxProvider\\Struct\\TaxProviderResult',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/TaxProvider/Payload/TaxProviderPayloadService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\TaxProvider\\Response\\TaxProviderResponse should not depend on Shopware\\Core\\Checkout\\Cart\\TaxProvider\\Struct\\TaxProviderResult',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/App/TaxProvider/Response/TaxProviderResponse.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\TaxProvider\\Response\\TaxProviderResponse should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTax',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/App/TaxProvider/Response/TaxProviderResponse.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\App\\TaxProvider\\Response\\TaxProviderResponse should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTaxCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 7,
    'path' => __DIR__ . '/src/Core/Framework/App/TaxProvider/Response/TaxProviderResponse.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Context should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Context.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\AttributeEntityCompiler::getFlags()" is 34, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/AttributeEntityCompiler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Cache\\EntityCacheKeyGenerator should not depend on Shopware\\Core\\System\\SalesChannel\\SalesChannelContext',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Cache/EntityCacheKeyGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Command\\CreateHydratorCommand::execute()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Command/CreateHydratorCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Command\\CreateHydratorCommand::generate()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Command/CreateHydratorCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Command\\DataAbstractionLayerValidateCommand::execute()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Command/DataAbstractionLayerValidateCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\Common\\SalesChannelRepositoryIterator should not depend on Shopware\\Core\\System\\SalesChannel\\Entity\\SalesChannelRepository',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/Common/SalesChannelRepositoryIterator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\Common\\SalesChannelRepositoryIterator should not depend on Shopware\\Core\\System\\SalesChannel\\SalesChannelContext',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/Common/SalesChannelRepositoryIterator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\CriteriaQueryBuilder::addSortings()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/CriteriaQueryBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 86, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityAggregator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityAggregator::hydrateResult()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityAggregator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 125, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityDefinitionQueryHelper.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityDefinitionQueryHelper::getFieldsOfAccessor()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityDefinitionQueryHelper.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityForeignKeyResolver::fetchAssociation()" is 29, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityForeignKeyResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityForeignKeyResolver should not depend on Shopware\\Core\\System\\Language\\LanguageDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityForeignKeyResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 101, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityHydrator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityHydrator::hydrateFields()" is 40, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityHydrator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 248, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityReader::fetchPaginatedOneToManyMapping()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityReader::joinBasic()" is 45, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityReader::loadManyToManyWithCriteria()" is 35, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityReader::loadOneToManyWithPagination()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityReader::loadOneToManyWithoutPagination()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityReader::loadToOne()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntitySearcher::search()" is 32, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntitySearcher.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntitySearcher should not depend on Shopware\\Core\\System\\NumberRange\\DataAbstractionLayer\\NumberRangeField',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntitySearcher.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 184, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityWriteGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityWriteGateway::executeCommands()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityWriteGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityWriteGateway::executeJsonUpdate()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityWriteGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityWriteGateway::prefetch()" is 46, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/EntityWriteGateway.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\FieldAccessorBuilder\\CustomFieldsAccessorBuilder should not depend on Shopware\\Core\\System\\CustomField\\CustomFieldService',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/FieldAccessorBuilder/CustomFieldsAccessorBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\FieldAccessorBuilder\\PriceFieldAccessorBuilder should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/FieldAccessorBuilder/PriceFieldAccessorBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\FieldAccessorBuilder\\PriceFieldAccessorBuilder should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/FieldAccessorBuilder/PriceFieldAccessorBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder::addForeignKeys()" is 42, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\Cms\\DataAbstractionLayer\\Field\\SlotConfigField',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\Cms\\DataAbstractionLayer\\Field\\SlotConfigField',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\Flow\\DataAbstractionLayer\\Field\\FlowTemplateConfigField',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\Flow\\DataAbstractionLayer\\Field\\FlowTemplateConfigField',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\MeasurementSystem\\Field\\MeasurementUnitsField',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\MeasurementSystem\\Field\\MeasurementUnitsField',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\CheapestPrice\\CheapestPriceField',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\CheapestPrice\\CheapestPriceField',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\SchemaBuilder should not depend on Shopware\\Core\\System\\NumberRange\\DataAbstractionLayer\\NumberRangeField',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Dbal/SchemaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 303, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\DefinitionValidator::findEntityNotices()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\DefinitionValidator::validate()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\DefinitionValidator::validateAssociations()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\DefinitionValidator::validateDataFieldNotPrefixedByEntityName()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\DefinitionValidator::validateForeignKeysReferenceUniqueKey()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\DefinitionValidator::validateManyToMany()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\DefinitionValidator::validateStruct()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityDefinition::getFields()" is 34, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityDefinition should not depend on Shopware\\Core\\Content\\Seo\\SeoUrl\\SeoUrlDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityDefinition should not depend on Shopware\\Core\\Content\\Seo\\SeoUrl\\SeoUrlDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CalculatedPrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CalculatedPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\QuantityPriceDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\QuantityPriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityTranslationDefinition should not depend on Shopware\\Core\\System\\Language\\LanguageDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/EntityTranslationDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Event\\EntityLoadedEventFactory should not depend on Shopware\\Core\\System\\SalesChannel\\Entity\\PartialSalesChannelEntityLoadedEvent',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Event/EntityLoadedEventFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Event\\EntityLoadedEventFactory should not depend on Shopware\\Core\\System\\SalesChannel\\Entity\\SalesChannelEntityLoadedEvent',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Event/EntityLoadedEventFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Event\\EntityLoadedEventFactory should not depend on Shopware\\Core\\System\\SalesChannel\\SalesChannelContext',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Event/EntityLoadedEventFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Facade\\SalesChannelRepositoryFacade should not depend on Shopware\\Core\\System\\SalesChannel\\Entity\\SalesChannelDefinitionInstanceRegistry',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Facade/SalesChannelRepositoryFacade.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Facade\\SalesChannelRepositoryFacade should not depend on Shopware\\Core\\System\\SalesChannel\\SalesChannelContext',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Facade/SalesChannelRepositoryFacade.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Facade\\SalesChannelRepositoryFacadeHookFactory should not depend on Shopware\\Core\\System\\SalesChannel\\Entity\\SalesChannelDefinitionInstanceRegistry',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Facade/SalesChannelRepositoryFacadeHookFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Field\\CreatedByField should not depend on Shopware\\Core\\System\\User\\UserDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/CreatedByField.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Field\\StateMachineStateField should not depend on Shopware\\Core\\System\\StateMachine\\Aggregation\\StateMachineState\\StateMachineStateDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/StateMachineStateField.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Field\\TranslatedField should not depend on Shopware\\Core\\System\\Language\\LanguageDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/TranslatedField.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Field\\UpdatedByField should not depend on Shopware\\Core\\System\\User\\UserDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Field/UpdatedByField.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CalculatedPrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CalculatedPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\ListPrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\ListPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\ReferencePrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\ReferencePrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\RegulationPrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\RegulationPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTax',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTax',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTaxCollection',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTaxCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRule',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRule',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRuleCollection',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CalculatedPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRuleCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CalculatedPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTax',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTax',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTaxCollection',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\CalculatedTaxCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRule',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRule',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRuleCollection',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CartPriceFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRuleCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CartPriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\CustomFieldsSerializer should not depend on Shopware\\Core\\System\\CustomField\\CustomFieldService',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/CustomFieldsSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\EmailFieldSerializer should not depend on Shopware\\Core\\Checkout\\Customer\\Service\\EmailIdnConverter',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/EmailFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\EmailFieldSerializer should not depend on Shopware\\Core\\Checkout\\Customer\\Service\\EmailIdnConverter',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/EmailFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\JsonFieldSerializer::validateMapping()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/JsonFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\ManyToManyAssociationFieldSerializer::normalize()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/ManyToManyAssociationFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PasswordFieldSerializer should not depend on Shopware\\Core\\System\\SystemConfig\\SystemConfigService',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PasswordFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer::encode()" is 32, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\AbsolutePriceDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\AbsolutePriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CurrencyPriceDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CurrencyPriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\PercentagePriceDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\PercentagePriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\PriceDefinitionInterface',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\PriceDefinitionInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\QuantityPriceDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\QuantityPriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRule',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceDefinitionFieldSerializer should not depend on Shopware\\Core\\Checkout\\Cart\\Tax\\Struct\\TaxRule',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceDefinitionFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceFieldSerializer::decode()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\PriceFieldSerializer::encode()" is 34, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/PriceFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\RemoteAddressFieldSerializer should not depend on Shopware\\Core\\System\\SystemConfig\\SystemConfigService',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/RemoteAddressFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\TranslationsAssociationFieldSerializer::normalize()" is 28, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/TranslationsAssociationFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\VariantListingConfigFieldSerializer should not depend on Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\VariantListingConfig',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/VariantListingConfigFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\VariantListingConfigFieldSerializer should not depend on Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\VariantListingConfig',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/FieldSerializer/VariantListingConfigFieldSerializer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Indexing\\EntityIndexerRegistry::index()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Indexing/EntityIndexerRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\RepositorySearchDetector::isSearchRequired()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/RepositorySearchDetector.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\ApiCriteriaValidator::validate()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/ApiCriteriaValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\CriteriaArrayConverter::convert()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/CriteriaArrayConverter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\Parser\\AggregationParser::aggregationToArray()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Parser/AggregationParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\Parser\\AggregationParser::parseAggregation()" is 51, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Parser/AggregationParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 85, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Parser/QueryStringParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\Parser\\QueryStringParser::fromArray()" is 67, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Parser/QueryStringParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\Parser\\SqlQueryParser::parseEqualsAnyFilter()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Parser/SqlQueryParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 115, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/RequestCriteriaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\RequestCriteriaBuilder::parse()" is 53, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/RequestCriteriaBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Search\\Term\\EntityScoreQueryBuilder::buildScoreQueries()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Search/Term/EntityScoreQueryBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\TechnicalNameExceptionHandler should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentException',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/TechnicalNameExceptionHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\TechnicalNameExceptionHandler should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/TechnicalNameExceptionHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\TechnicalNameExceptionHandler should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingException',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/TechnicalNameExceptionHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\TechnicalNameExceptionHandler should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/TechnicalNameExceptionHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\TechnicalNameExceptionHandler should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportException',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/TechnicalNameExceptionHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\TechnicalNameExceptionHandler should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/TechnicalNameExceptionHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\TranslationEntity should not depend on Shopware\\Core\\System\\Language\\LanguageEntity',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/TranslationEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Util\\AfterSort::sort()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Util/AfterSort.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Validation\\EntityExists::__construct()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Validation/EntityExists.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Validation\\EntityNotExists::__construct()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Validation/EntityNotExists.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 224, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/VersionManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\VersionManager::addCloneAssociations()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/VersionManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\VersionManager::buildWrites()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/VersionManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\VersionManager::filterPropertiesForClone()" is 73, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/VersionManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\VersionManager::writeAuditLog()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/VersionManager.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Write\\Command\\WriteCommandQueue::getCommandsInOrder()" is 36, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/Command/WriteCommandQueue.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 132, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/EntityWriteResultFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Write\\EntityWriteResultFactory::buildQueueResults()" is 45, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/EntityWriteResultFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Write\\EntityWriter::extractDeleteCommands()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/EntityWriter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Write\\EntityWriter should not depend on Shopware\\Core\\System\\Language\\LanguageLoaderInterface',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/EntityWriter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 124, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/WriteCommandExtractor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DataAbstractionLayer\\Write\\WriteCommandExtractor::normalizeSingle()" is 42, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/WriteCommandExtractor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Write\\WriteContext should not depend on Shopware\\Core\\System\\Language\\LanguageDefinition',
    'identifier' => 'phpat.dataAbstractionLayerShouldStayGeneric',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DataAbstractionLayer/Write/WriteContext.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Checkout\\Customer\\CustomerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Checkout\\Order\\OrderDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Checkout\\Promotion\\PromotionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Category\\CategoryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Flow\\FlowDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailHeaderFooter\\MailHeaderFooterDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\MailTemplate\\MailTemplateDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Media\\MediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Newsletter\\Aggregate\\NewsletterRecipient\\NewsletterRecipientDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\ProductStream\\ProductStreamDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductManufacturer\\ProductManufacturerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductReview\\ProductReviewDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Property\\PropertyGroupDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Command\\DemodataCommand should not depend on Shopware\\Core\\Content\\Rule\\RuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Command/DemodataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\DemodataContext should not depend on Shopware\\Core\\Content\\Media\\MediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/DemodataContext.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\CategoryGenerator should not depend on Shopware\\Core\\Content\\Category\\CategoryCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/CategoryGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\CategoryGenerator should not depend on Shopware\\Core\\Content\\Category\\CategoryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/CategoryGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\CategoryGenerator should not depend on Shopware\\Core\\Content\\Cms\\CmsPageCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/CategoryGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\CustomerGenerator should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerGroup\\CustomerGroupCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/CustomerGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\CustomerGenerator should not depend on Shopware\\Core\\Checkout\\Customer\\CustomerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/CustomerGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\CustomerGenerator should not depend on Shopware\\Core\\Test\\TestDefaults',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/CustomerGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator::createFlows()" is 40, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderDelivery\\OrderDeliveryStates',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 5,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionStates',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 10,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Checkout\\Order\\OrderStates',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowSequence\\FlowSequenceCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowSequence\\FlowSequenceEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 9,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Api\\FlowActionCollector',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Api\\FlowActionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\AddCustomerTagAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\AddOrderTagAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\GenerateDocumentAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\RemoveCustomerTagAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\RemoveOrderTagAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SendMailAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetOrderStateAction',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\FlowGenerator should not depend on Shopware\\Core\\Content\\Flow\\FlowDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/FlowGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MailHeaderFooterGenerator should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailHeaderFooter\\MailHeaderFooterDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MailHeaderFooterGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MailTemplateGenerator should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailTemplateType\\MailTemplateTypeCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MailTemplateGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MailTemplateGenerator should not depend on Shopware\\Core\\Content\\MailTemplate\\MailTemplateDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MailTemplateGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MediaGenerator should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaDefaultFolder\\MediaDefaultFolderCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MediaGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MediaGenerator should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaFolder\\MediaFolderCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MediaGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MediaGenerator should not depend on Shopware\\Core\\Content\\Media\\File\\FileNameProvider',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MediaGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MediaGenerator should not depend on Shopware\\Core\\Content\\Media\\File\\FileSaver',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MediaGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MediaGenerator should not depend on Shopware\\Core\\Content\\Media\\File\\MediaFile',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MediaGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\MediaGenerator should not depend on Shopware\\Core\\Content\\Media\\MediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/MediaGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\NewsletterRecipientGenerator should not depend on Shopware\\Core\\Content\\Newsletter\\Aggregate\\NewsletterRecipient\\NewsletterRecipientDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/NewsletterRecipientGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\NewsletterRecipientGenerator should not depend on Shopware\\Core\\Content\\Newsletter\\SalesChannel\\NewsletterSubscribeRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/NewsletterRecipientGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\CartCalculator',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\LineItem\\LineItem',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Order\\OrderConversionContext',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Order\\OrderConverter',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\PercentagePriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\SalesChannel\\CartService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Order\\OrderDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\OrderGenerator should not depend on Shopware\\Core\\Checkout\\Promotion\\Cart\\PromotionItemBuilder',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/OrderGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\ProductGenerator should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductVisibility\\ProductVisibilityDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/ProductGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\ProductGenerator should not depend on Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\StatesUpdater',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/ProductGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\ProductGenerator should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 5,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/ProductGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\ProductManufacturerGenerator should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductManufacturer\\ProductManufacturerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/ProductManufacturerGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\ProductReviewGenerator should not depend on Shopware\\Core\\Checkout\\Customer\\Service\\ProductReviewCountService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/ProductReviewGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\ProductReviewGenerator should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductReview\\ProductReviewDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/ProductReviewGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\ProductStreamGenerator should not depend on Shopware\\Core\\Content\\ProductStream\\ProductStreamDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/ProductStreamGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\PromotionGenerator should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionDiscount\\PromotionDiscountEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 6,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/PromotionGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\PromotionGenerator should not depend on Shopware\\Core\\Checkout\\Promotion\\PromotionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/PromotionGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\PropertyGroupGenerator should not depend on Shopware\\Core\\Content\\Property\\PropertyGroupCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/PropertyGroupGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\PropertyGroupGenerator should not depend on Shopware\\Core\\Content\\Property\\PropertyGroupDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/PropertyGroupGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsPriceRule',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Checkout\\Customer\\Rule\\CustomerGroupRule',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Checkout\\Customer\\Rule\\DaysSinceFirstLoginRule',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Content\\Rule\\RuleCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Content\\Rule\\RuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\Generator\\RuleGenerator should not depend on Shopware\\Core\\Test\\TestDefaults',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/Generator/RuleGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Demodata\\PersonalData\\CleanPersonalDataCommand should not depend on Shopware\\Core\\Checkout\\Customer\\CustomerCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Demodata/PersonalData/CleanPersonalDataCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Cart\\CartDataCollectorInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Cart\\CartProcessorInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Cart\\CartValidatorInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Cart\\LineItemFactoryHandler\\LineItemFactoryInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Cart\\LineItem\\Group\\LineItemGroupPackagerInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Cart\\LineItem\\Group\\LineItemGroupSorterInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Cart\\TaxProvider\\AbstractTaxProvider',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Customer\\Password\\LegacyEncoder\\LegacyEncoderInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Document\\Renderer\\AbstractDocumentRenderer',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\AbstractPaymentHandler',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Promotion\\Cart\\Discount\\Filter\\FilterPickerInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Checkout\\Promotion\\Cart\\Discount\\Filter\\FilterSorterInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Content\\Cms\\DataResolver\\Element\\CmsElementResolverInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\FlowStorer',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Content\\Product\\SalesChannel\\Listing\\Filter\\AbstractListingFilterHandler',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Content\\Product\\SalesChannel\\Listing\\Processor\\AbstractListingProcessor',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Content\\Seo\\SeoUrlRoute\\SeoUrlRouteInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Content\\Seo\\SeoUrlRoute\\SeoUrlRouteLoaderInterface',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\AutoconfigureCompilerPass should not depend on Shopware\\Core\\Content\\Sitemap\\Provider\\AbstractUrlProvider',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/AutoconfigureCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\FeatureFlagCompilerPass::process()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/FeatureFlagCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 95, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/McpToolDiscoveryCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\McpToolDiscoveryCompilerPass::assignElementsToServers()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/McpToolDiscoveryCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DependencyInjection\\CompilerPass\\McpToolDiscoveryCompilerPass::pruneUnmatchedPatterns()" is 35, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/CompilerPass/McpToolDiscoveryCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\DependencyInjection\\Configuration::createHttpCacheSection()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/Configuration.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\Configuration should not depend on Shopware\\Core\\Content\\Media\\File\\DownloadResponseGenerator',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/Configuration.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\DependencyInjection\\Configuration should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/DependencyInjection/Configuration.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfigSalesChannel\\DocumentBaseConfigSalesChannelCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfigSalesChannel\\DocumentBaseConfigSalesChannelDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfigSalesChannel\\DocumentBaseConfigSalesChannelEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfig\\DocumentBaseConfigCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfig\\DocumentBaseConfigDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfig\\DocumentBaseConfigEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\DocumentCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\DocumentDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\DocumentEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\SalesChannel\\AbstractDocumentRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\SalesChannel\\DocumentRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Service\\ReferenceInvoiceLoader',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Deprecation\\ClassAliasRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Struct\\RenderedDocument',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Deprecation/ClassAliasRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Cart\\Event\\CheckoutOrderPlacedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerAccountRecoverRequestEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerBeforeLoginEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerDeletedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerDoubleOptInRegistrationEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerGroupRegistrationAccepted',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerGroupRegistrationDeclined',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLoginEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLogoutEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerPasswordChangedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerRegisterEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\DoubleOptInGuestOrderEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\GuestCustomerRegisterEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Event\\DocumentDeletedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Event\\DocumentGeneratedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Checkout\\Order\\Event\\OrderPaymentMethodChangedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\ContactForm\\Event\\ContactFormEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailBeforeSentEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailBeforeValidateEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailSentEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterConfirmEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterRegisterEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterUnsubscribeEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\ProductExport\\Event\\ProductExportLoggingEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\Product\\SalesChannel\\Review\\Event\\ReviewFormEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEventRegistry should not depend on Shopware\\Core\\Content\\RevocationRequest\\Event\\RevocationRequestEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEventRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Cart\\Event\\CheckoutOrderPlacedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerAccountRecoverRequestEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerBeforeLoginEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerDeletedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerDoubleOptInRegistrationEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerGroupRegistrationAccepted',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerGroupRegistrationDeclined',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLoginEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLogoutEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerRegisterEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\DoubleOptInGuestOrderEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\GuestCustomerRegisterEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Event\\DocumentDeletedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Event\\DocumentGeneratedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Checkout\\Order\\Event\\OrderPaymentMethodChangedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\ContactForm\\Event\\ContactFormEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailBeforeSentEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailBeforeValidateEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailSentEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterConfirmEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterRegisterEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterUnsubscribeEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\ProductExport\\Event\\ProductExportLoggingEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Event\\BusinessEvents should not depend on Shopware\\Core\\Content\\Product\\SalesChannel\\Review\\Event\\ReviewFormEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Event/BusinessEvents.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Framework should not depend on Shopware\\Core\\Framework\\Test\\DependencyInjection\\CompilerPass\\ContainerVisibilityCompilerPass',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Framework.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Framework should not depend on Shopware\\Core\\Framework\\Test\\RateLimiter\\DisableRateLimiterCompilerPass',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Framework.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Event\\ContextGatewayCommandsCollectedEvent should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Command/Event/ContextGatewayCommandsCollectedEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Event\\ContextGatewayCommandsCollectedEvent should not depend on Shopware\\Core\\Checkout\\Cart\\Hook\\CartAware',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Command/Event/ContextGatewayCommandsCollectedEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Handler\\ChangeCheckoutOptionsCommandHandler should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Command/Handler/ChangeCheckoutOptionsCommandHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Handler\\ChangeCheckoutOptionsCommandHandler should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Command/Handler/ChangeCheckoutOptionsCommandHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Handler\\LoginCustomerCommandHandler should not depend on Shopware\\Core\\Checkout\\Customer\\SalesChannel\\AccountService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Command/Handler/LoginCustomerCommandHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Handler\\RegisterCustomerCommandHandler should not depend on Shopware\\Core\\Checkout\\Customer\\SalesChannel\\AbstractRegisterRoute',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Command/Handler/RegisterCustomerCommandHandler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Struct\\ContextGatewayPayloadStruct should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Command/Struct/ContextGatewayPayloadStruct.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\Extension\\ContextGatewayRouteExtension should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/Extension/ContextGatewayRouteExtension.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\SalesChannel\\AbstractContextGatewayRoute should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/SalesChannel/AbstractContextGatewayRoute.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Gateway\\Context\\SalesChannel\\ContextGatewayRoute should not depend on Shopware\\Core\\Checkout\\Cart\\Cart',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Gateway/Context/SalesChannel/ContextGatewayRoute.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\JWT\\SalesChannel\\JWTGenerator::buildToken()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/JWT/SalesChannel/JWTGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 108, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Command/DebugMcpCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Command\\DebugMcpCommand::execute()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Command/DebugMcpCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Command\\DebugMcpCommand::renderDetail()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Command/DebugMcpCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Command\\DebugMcpCommand::renderToolDetail()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Command/DebugMcpCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Controller\\McpServerController::checkAllowlistEarlyReject()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Controller/McpServerController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\McpJsonRpcResponse::parseResult()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/McpJsonRpcResponse.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Resource\\ExtensionsResource::resolveStatuses()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Resource/ExtensionsResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Resource\\FlowActionsResource should not depend on Shopware\\Core\\Content\\Flow\\Api\\FlowActionCollector',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Resource/FlowActionsResource.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Tool\\EntityDeleteTool::buildDeletePayload()" is 37, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/EntityDeleteTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\MediaUploadTool should not depend on Shopware\\Core\\Content\\Media\\Upload\\MediaUploadParameters',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/MediaUploadTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\MediaUploadTool should not depend on Shopware\\Core\\Content\\Media\\Upload\\MediaUploadService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/MediaUploadTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Tool\\OrderStateTool::__invoke()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/OrderStateTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\OrderStateTool should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderDelivery\\OrderDeliveryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/OrderStateTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\OrderStateTool should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderDelivery\\OrderDeliveryEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/OrderStateTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\OrderStateTool should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/OrderStateTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\OrderStateTool should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/OrderStateTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\OrderStateTool should not depend on Shopware\\Core\\Checkout\\Order\\OrderDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/OrderStateTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Mcp\\Tool\\OrderStateTool should not depend on Shopware\\Core\\Checkout\\Order\\OrderEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 6,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/OrderStateTool.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Mcp\\Tool\\Search\\ToolSearch::scoreTool()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Mcp/Tool/Search/ToolSearch.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\MessageQueue\\Command\\ScheduledTaskRunner::execute()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/MessageQueue/Command/ScheduledTaskRunner.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Migration\\MigrationCollection::loadMigrationSteps()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Migration/MigrationCollection.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Migration\\MigrationIndexerSafeguard::extractDmlOperations()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Migration/MigrationIndexerSafeguard.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Plugin\\Command\\Lifecycle\\PluginInstallCommand::execute()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/Command/Lifecycle/PluginInstallCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Plugin\\Command\\PluginCreateCommand::__invoke()" is 45, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/Command/PluginCreateCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Plugin\\Command\\PluginListCommand::execute()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/Command/PluginListCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Plugin\\KernelPluginLoader\\KernelPluginLoader::registerPluginNamespaces()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/KernelPluginLoader/KernelPluginLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Plugin\\PluginDefinition should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/PluginDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Plugin\\PluginEntity should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/PluginEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Plugin\\PluginService::refreshPlugins()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Plugin/PluginService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\RateLimiter\\NoLimitRateLimiterFactory should not depend on Shopware\\Core\\Framework\\Test\\RateLimiter\\DisableRateLimiterCompilerPass',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/RateLimiter/NoLimitRateLimiterFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Routing\\ApiRequestContextResolver should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Routing/ApiRequestContextResolver.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Routing\\PaymentScopeWhitelist should not depend on Shopware\\Core\\Checkout\\Payment\\Controller\\PaymentController',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Routing/PaymentScopeWhitelist.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Routing\\SessionContextTokenSubscriber should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLoginEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Routing/SessionContextTokenSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Routing\\SessionContextTokenSubscriber should not depend on Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLogoutEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Routing/SessionContextTokenSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Rule\\Container\\MatchAllLineItemsRule::match()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Rule/Container/MatchAllLineItemsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Rule\\Container\\MatchAllLineItemsRule should not depend on Shopware\\Core\\Checkout\\Cart\\LineItem\\LineItem',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Rule/Container/MatchAllLineItemsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Rule\\Container\\MatchAllLineItemsRule should not depend on Shopware\\Core\\Checkout\\Cart\\LineItem\\LineItemCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Rule/Container/MatchAllLineItemsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Rule\\Container\\MatchAllLineItemsRule should not depend on Shopware\\Core\\Checkout\\Cart\\Rule\\CartRuleScope',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Rule/Container/MatchAllLineItemsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Rule\\Container\\MatchAllLineItemsRule should not depend on Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemScope',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Rule/Container/MatchAllLineItemsRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Rule\\Container\\ZipCodeRule should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerAddress\\CustomerAddressEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Framework/Rule/Container/ZipCodeRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Rule\\CustomFieldRule should not depend on Shopware\\Core\\Checkout\\Cart\\Price\\Struct\\CartPrice',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Rule/CustomFieldRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Script\\ServiceStubs should not depend on Shopware\\Core\\Checkout\\Cart\\Facade\\CartFacade',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Script\\ServiceStubs should not depend on Shopware\\Core\\Checkout\\Cart\\Facade\\PriceFactory',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Sso\\SsoUser\\SsoUserInvitationMailService should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailTemplateType\\MailTemplateTypeCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Sso/SsoUser/SsoUserInvitationMailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Sso\\SsoUser\\SsoUserInvitationMailService should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailTemplateType\\MailTemplateTypeEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Sso/SsoUser/SsoUserInvitationMailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Sso\\SsoUser\\SsoUserInvitationMailService should not depend on Shopware\\Core\\Content\\MailTemplate\\MailTemplateCollection',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Sso/SsoUser/SsoUserInvitationMailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Sso\\SsoUser\\SsoUserInvitationMailService should not depend on Shopware\\Core\\Content\\MailTemplate\\MailTemplateEntity',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Sso/SsoUser/SsoUserInvitationMailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Sso\\SsoUser\\SsoUserInvitationMailService should not depend on Shopware\\Core\\Content\\Mail\\Service\\AbstractMailService',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Sso/SsoUser/SsoUserInvitationMailService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerAddress\\CustomerAddressDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerGroupRegistrationSalesChannel\\CustomerGroupRegistrationSalesChannelDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerGroupTranslation\\CustomerGroupTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerGroup\\CustomerGroupDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerRecovery\\CustomerRecoveryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Customer\\Aggregate\\CustomerTag\\CustomerTagDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Customer\\CustomerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfigSalesChannel\\DocumentBaseConfigSalesChannelDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfig\\DocumentBaseConfigDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\DocumentV2\\DocumentDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Document\\Aggregate\\DocumentTypeTranslation\\DocumentTypeTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Document\\Aggregate\\DocumentType\\DocumentTypeDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderAddress\\OrderAddressDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderCustomer\\OrderCustomerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderDeliveryPosition\\OrderDeliveryPositionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderDelivery\\OrderDeliveryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderLineItem\\OrderLineItemDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTag\\OrderTagDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\Aggregate\\OrderTransaction\\OrderTransactionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Order\\OrderDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Payment\\Aggregate\\PaymentMethodTranslation\\PaymentMethodTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionCartRule\\PromotionCartRuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionDiscountPrice\\PromotionDiscountPriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionDiscountRule\\PromotionDiscountRuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionDiscount\\PromotionDiscountDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionIndividualCode\\PromotionIndividualCodeDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionOrderRule\\PromotionOrderRuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionPersonaCustomer\\PromotionPersonaCustomerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionPersonaRule\\PromotionPersonaRuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionSalesChannel\\PromotionSalesChannelDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionSetGroupRule\\PromotionSetGroupRuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionSetGroup\\PromotionSetGroupDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\Aggregate\\PromotionTranslation\\PromotionTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Checkout\\Promotion\\PromotionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Category\\Aggregate\\CategoryTag\\CategoryTagDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Category\\Aggregate\\CategoryTranslation\\CategoryTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Category\\CategoryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Cms\\Aggregate\\CmsBlock\\CmsBlockDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Cms\\Aggregate\\CmsPageTranslation\\CmsPageTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Cms\\Aggregate\\CmsSection\\CmsSectionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Cms\\Aggregate\\CmsSlotTranslation\\CmsSlotTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Cms\\Aggregate\\CmsSlot\\CmsSlotDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Cms\\CmsPageDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ImportExport\\Aggregate\\ImportExportFile\\ImportExportFileDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ImportExport\\Aggregate\\ImportExportLog\\ImportExportLogDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailHeaderFooterTranslation\\MailHeaderFooterTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailHeaderFooter\\MailHeaderFooterDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailTemplateMedia\\MailTemplateMediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailTemplateTranslation\\MailTemplateTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailTemplateTypeTranslation\\MailTemplateTypeTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailTemplateType\\MailTemplateTypeDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\MailTemplate\\MailTemplateDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaDefaultFolder\\MediaDefaultFolderDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaFolderConfigurationMediaThumbnailSize\\MediaFolderConfigurationMediaThumbnailSizeDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaFolderConfiguration\\MediaFolderConfigurationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaFolder\\MediaFolderDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaTag\\MediaTagDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaThumbnailSize\\MediaThumbnailSizeDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaThumbnail\\MediaThumbnailDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\Aggregate\\MediaTranslation\\MediaTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Media\\MediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Newsletter\\Aggregate\\NewsletterRecipientTag\\NewsletterRecipientTagDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Newsletter\\Aggregate\\NewsletterRecipient\\NewsletterRecipientDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ProductExport\\ProductExportDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ProductStream\\Aggregate\\ProductStreamFilter\\ProductStreamFilterDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ProductStream\\Aggregate\\ProductStreamTranslation\\ProductStreamTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\ProductStream\\ProductStreamDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCategoryTree\\ProductCategoryTreeDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCategory\\ProductCategoryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductConfiguratorSetting\\ProductConfiguratorSettingDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCrossSellingAssignedProducts\\ProductCrossSellingAssignedProductsDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCrossSellingTranslation\\ProductCrossSellingTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCrossSelling\\ProductCrossSellingDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductCustomFieldSet\\ProductCustomFieldSetDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductFeatureSetTranslation\\ProductFeatureSetTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductFeatureSet\\ProductFeatureSetDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductKeywordDictionary\\ProductKeywordDictionaryDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductManufacturerTranslation\\ProductManufacturerTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductManufacturer\\ProductManufacturerDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductMedia\\ProductMediaDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductOption\\ProductOptionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductPrice\\ProductPriceDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductProperty\\ProductPropertyDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductReview\\ProductReviewDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductSearchKeyword\\ProductSearchKeywordDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductTag\\ProductTagDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductTranslation\\ProductTranslationDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductVisibility\\ProductVisibilityDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Product\\SalesChannel\\Sorting\\ProductSortingDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Rule\\Aggregate\\RuleCondition\\RuleConditionDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Rule\\RuleDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Seo\\SeoUrlTemplate\\SeoUrlTemplateDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Store\\Helper\\PermissionCategorization should not depend on Shopware\\Core\\Content\\Seo\\SeoUrl\\SeoUrlDefinition',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Store/Helper/PermissionCategorization.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Struct\\ArrayEntity should not depend on Shopware\\Core\\Framework\\DataAbstractionLayer\\Entity',
    'identifier' => 'phpat.foundationModulesShouldStayIndependent',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/Struct/ArrayEntity.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Telemetry\\Telemetry::instrument()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Telemetry/Telemetry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Util\\HtmlSanitizer::getConfig()" is 57, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Util/HtmlSanitizer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Validation\\Constraint\\ArrayOfTypeValidator::validate()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/Constraint/ArrayOfTypeValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 102, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/HappyPathValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Validation\\HappyPathValidator::validateConstraint()" is 93, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Validation/HappyPathValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Webhook\\Hookable should not depend on Shopware\\Core\\Content\\Media\\Event\\MediaUploadedEvent',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Framework/Webhook/Hookable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Framework\\Webhook\\Hookable\\HookableBusinessEvent::checkPermissionsForDataType()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Webhook/Hookable/HookableBusinessEvent.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Webhook\\Hookable\\HookableEventFactory should not depend on Shopware\\Core\\Content\\Flow\\Dispatching\\StorableFlow',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Webhook/Hookable/HookableEventFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Webhook\\Validation\\WebhookTargetValidator should not depend on Shopware\\Core\\Content\\Media\\File\\TrustedUrlResolver',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 3,
    'path' => __DIR__ . '/src/Core/Framework/Webhook/Validation/WebhookTargetValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Framework\\Webhook\\Validation\\WebhookTargetValidator should not depend on Shopware\\Core\\Content\\Media\\MediaException',
    'identifier' => 'phpat.frameworkShouldNotDependOnBusinessDomains',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Framework/Webhook/Validation/WebhookTargetValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Installer\\Controller\\ShopConfigurationController::shopConfiguration()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Installer/Controller/ShopConfigurationController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Kernel::getCacheHash()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Kernel.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Kernel::registerBundles()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Kernel.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Maintenance\\System\\Command\\SystemSetupCommand::execute()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Maintenance/System/Command/SystemSetupCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1536233560BasicData should not depend on Shopware\\Core\\Content\\Category\\CategoryDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1536233560BasicData.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1536233560BasicData should not depend on Shopware\\Core\\System\\DeliveryTime\\DeliveryTimeEntity',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1536233560BasicData.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Migration\\V6_3\\Migration1570621541UpdateDefaultMailTemplates::update()" is 27, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1570621541UpdateDefaultMailTemplates.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Migration\\V6_3\\Migration1571990395UpdateDefaultStatusMailTemplates::updateMailTemplateTranslation()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1571990395UpdateDefaultStatusMailTemplates.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1572193798TaxRule should not depend on Shopware\\Core\\System\\Tax\\Aggregate\\TaxRuleType\\TaxRuleTypeDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1572193798TaxRule.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1586173614AddAdditionalImportExportProfiles should not depend on Shopware\\Core\\Content\\Newsletter\\Aggregate\\NewsletterRecipient\\NewsletterRecipientDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1586173614AddAdditionalImportExportProfiles.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1586173614AddAdditionalImportExportProfiles should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductConfiguratorSetting\\ProductConfiguratorSettingDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1586173614AddAdditionalImportExportProfiles.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1586173614AddAdditionalImportExportProfiles should not depend on Shopware\\Core\\Content\\Property\\Aggregate\\PropertyGroupOption\\PropertyGroupOptionDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1586173614AddAdditionalImportExportProfiles.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1590758953ProductFeatureSet should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductFeatureSet\\ProductFeatureSetDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1590758953ProductFeatureSet.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1595422169AddProductSorting should not depend on Shopware\\Core\\Content\\Product\\SalesChannel\\Sorting\\ProductSortingDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1595422169AddProductSorting.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1600338271AddTopsellerSorting should not depend on Shopware\\Core\\Content\\Product\\SalesChannel\\Sorting\\ProductSortingDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1600338271AddTopsellerSorting.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1607415095PromotionRedemptionsNullable should not depend on Shopware\\Core\\Checkout\\Promotion\\PromotionDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1607415095PromotionRedemptionsNullable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1607581276AddProductSearchConfigurationDefaults should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductSearchConfigField\\ProductSearchConfigFieldDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1607581276AddProductSearchConfigurationDefaults.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_3\\Migration1607581276AddProductSearchConfigurationDefaults should not depend on Shopware\\Core\\Content\\Product\\Aggregate\\ProductSearchConfig\\ProductSearchConfigDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_3/Migration1607581276AddProductSearchConfigurationDefaults.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1616076922AppPaymentMethod should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1616076922AppPaymentMethod.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1619428555AddDefaultMailFooter should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailHeaderFooterTranslation\\MailHeaderFooterTranslationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1619428555AddDefaultMailFooter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1619428555AddDefaultMailFooter should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailHeaderFooter\\MailHeaderFooterDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1619428555AddDefaultMailFooter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1620820321AddDefaultDomainForHeadlessSaleschannel should not depend on Shopware\\Core\\System\\SalesChannel\\Aggregate\\SalesChannelDomain\\SalesChannelDomainDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1620820321AddDefaultDomainForHeadlessSaleschannel.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Migration\\V6_4\\Migration1625583619MoveDataFromEventActionToFlow::insertFlow()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1625583619MoveDataFromEventActionToFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1625583619MoveDataFromEventActionToFlow should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowSequence\\FlowSequenceDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1625583619MoveDataFromEventActionToFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1625583619MoveDataFromEventActionToFlow should not depend on Shopware\\Core\\Content\\Flow\\FlowDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1625583619MoveDataFromEventActionToFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1625583619MoveDataFromEventActionToFlow should not depend on Shopware\\Core\\Content\\Rule\\Aggregate\\RuleCondition\\RuleConditionDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1625583619MoveDataFromEventActionToFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1625583619MoveDataFromEventActionToFlow should not depend on Shopware\\Core\\Content\\Rule\\RuleDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1625583619MoveDataFromEventActionToFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1625831469AddImportExportCrossSellingProfile should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileTranslationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1625831469AddImportExportCrossSellingProfile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1626696809AddImportExportCustomerProfile should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileTranslationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1626696809AddImportExportCustomerProfile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1627983652AddOrderExportProfile should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileTranslationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1627983652AddOrderExportProfile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1636449347AddImportExportAdvancedPricesProfile should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileTranslationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1636449347AddImportExportAdvancedPricesProfile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1636971615AddImportExportPromotionDiscountProfile should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileTranslationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1636971615AddImportExportPromotionDiscountProfile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1648803451FixInvalidMigrationOfBusinessEventToFlow should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowSequence\\FlowSequenceDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1648803451FixInvalidMigrationOfBusinessEventToFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull should not depend on Shopware\\Core\\Content\\Category\\CategoryDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1650620993SetDefaultCmsPagesAndSetCategoryCmsPageToNull.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1658786605AddAddressFormatIntoCountryTranslation should not depend on Shopware\\Core\\System\\Country\\CountryDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1658786605AddAddressFormatIntoCountryTranslation.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1659257296GenerateFlowTemplateDataFromEventAction should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowTemplate\\FlowTemplateDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1659257296GenerateFlowTemplateDataFromEventAction.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1659257396DownloadFlow should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowTemplate\\FlowTemplateDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1659257396DownloadFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1667983492UpdateQueuedTasksToSkipped should not depend on Shopware\\Core\\Framework\\MessageQueue\\ScheduledTask\\ScheduledTaskDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1667983492UpdateQueuedTasksToSkipped.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_4\\Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplate should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowTemplate\\FlowTemplateDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_4/Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplate.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_5\\Migration1676272000AddAccountTypeToCustomer should not depend on Shopware\\Core\\Checkout\\Customer\\CustomerEntity',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_5/Migration1676272000AddAccountTypeToCustomer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_5\\Migration1676272001AddAccountTypeToCustomerProfileImportExport should not depend on Shopware\\Core\\Checkout\\Customer\\CustomerDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_5/Migration1676272001AddAccountTypeToCustomerProfileImportExport.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_5\\Migration1689257577AddMissingTransactionMailFlow should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowTemplate\\FlowTemplateDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_5/Migration1689257577AddMissingTransactionMailFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_5\\Migration1691057865UpdateSalutationDefaultForCustomer should not depend on Shopware\\Core\\System\\Salutation\\SalutationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_5/Migration1691057865UpdateSalutationDefaultForCustomer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_5\\Migration1692279790AppShippingMethod should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_5/Migration1692279790AppShippingMethod.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_5\\Migration1697112043AddPaymentAndShippingTechnicalName should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_5/Migration1697112043AddPaymentAndShippingTechnicalName.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_5\\Migration1697112043AddPaymentAndShippingTechnicalName should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_5/Migration1697112043AddPaymentAndShippingTechnicalName.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Migration\\V6_6\\Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlots::migrateCmsSlots()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_6/Migration1711418838ReplaceSortingOptionKeysWithSortingOptionIdsInCmsSlots.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Migration\\V6_6\\Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIds::migrateCategoryConfig()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_6/Migration1711461572ReplaceSortingOptionKeysWithSortingOptionIds.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_6\\Migration1711461580SetSystemDefaultForDefaultMailFooter should not depend on Shopware\\Core\\Content\\MailTemplate\\Aggregate\\MailHeaderFooter\\MailHeaderFooterDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_6/Migration1711461580SetSystemDefaultForDefaultMailFooter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_6\\Migration1716196653AddTechnicalNameToImportExportProfile should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_6/Migration1716196653AddTechnicalNameToImportExportProfile.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_6\\Migration1726049442UpdateVariantListingConfigInProductTable should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_6/Migration1726049442UpdateVariantListingConfigInProductTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1697112044PaymentAndShippingTechnicalNameRequired should not depend on Shopware\\Core\\Checkout\\Payment\\PaymentMethodDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1697112044PaymentAndShippingTechnicalNameRequired.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1697112044PaymentAndShippingTechnicalNameRequired should not depend on Shopware\\Core\\Checkout\\Shipping\\ShippingMethodDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1697112044PaymentAndShippingTechnicalNameRequired.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1717573310ImportExportTechnicalNameRequired should not depend on Shopware\\Core\\Content\\ImportExport\\ImportExportProfileDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1717573310ImportExportTechnicalNameRequired.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1773420826AddSalutationPositionColumn should not depend on Shopware\\Core\\System\\Salutation\\SalutationDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 4,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1773420826AddSalutationPositionColumn.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1775200001IncreaseProductDisplayGroupLength should not depend on Shopware\\Core\\Content\\Product\\ProductDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1775200001IncreaseProductDisplayGroupLength.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1776691515SetDefaultCmsPageIdForCategories should not depend on Shopware\\Core\\Content\\Category\\CategoryDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1776691515SetDefaultCmsPageIdForCategories.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1776770187AddDocumentFileTable should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentFile\\DocumentFileDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1776770187AddDocumentFileTable.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1778072247AddDocumentBaseConfigTypedColumns should not depend on Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfig\\DocumentBaseConfigDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1778072247AddDocumentBaseConfigTypedColumns.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1782308630AddBusinessTimeZoneToSalesChannel should not depend on Shopware\\Core\\System\\SalesChannel\\SalesChannelDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1782308630AddBusinessTimeZoneToSalesChannel.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1785327592AddTranslationAutoUpdateToLanguage should not depend on Shopware\\Core\\System\\Language\\LanguageDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1785327592AddTranslationAutoUpdateToLanguage.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1786967900AddFailedPaymentMailTemplateAndFlow should not depend on Shopware\\Core\\Content\\Flow\\Aggregate\\FlowTemplate\\FlowTemplateDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1786967900AddFailedPaymentMailTemplateAndFlow.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1787311123AddSourceTypeToStateMachineHistory should not depend on Shopware\\Core\\System\\StateMachine\\Aggregation\\StateMachineHistory\\StateMachineHistoryDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1787311123AddSourceTypeToStateMachineHistory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\Migration\\V6_7\\Migration1788985964WidenSeoUrlRouteName should not depend on Shopware\\Core\\Content\\Seo\\SeoUrl\\SeoUrlDefinition',
    'identifier' => 'phpat.migrationsShouldNotUseEntityLayer',
    'count' => 2,
    'path' => __DIR__ . '/src/Core/Migration/V6_7/Migration1788985964WidenSeoUrlRouteName.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Profiling\\Doctrine\\ConnectionProfiler::sanitizeQuery()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Profiling/Doctrine/ConnectionProfiler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\CustomEntity\\Schema\\CustomEntityPersister::update()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/CustomEntity/Schema/CustomEntityPersister.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\CustomEntity\\Schema\\CustomEntitySchemaUpdater::cleanup()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/CustomEntity/Schema/CustomEntitySchemaUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\CustomEntity\\Schema\\DynamicFieldFactory::defineField()" is 36, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/CustomEntity/Schema/DynamicFieldFactory.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\CustomEntity\\Schema\\SchemaUpdater::addColumns()" is 33, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/CustomEntity/Schema/SchemaUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\CustomEntity\\Xml\\CustomEntityXmlSchemaValidator::validate()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/CustomEntity/Xml/CustomEntityXmlSchemaValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\DependencyInjection\\CompilerPass\\SalesChannelEntityCompilerPass::addExtensions()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/DependencyInjection/CompilerPass/SalesChannelEntityCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\DependencyInjection\\CompilerPass\\SalesChannelEntityCompilerPass::collectDefinitions()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/DependencyInjection/CompilerPass/SalesChannelEntityCompilerPass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 98, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SalesChannel/Api/StructEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SalesChannel\\Api\\StructEncoder::encodeExtensions()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SalesChannel/Api/StructEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SalesChannel\\Api\\StructEncoder::encodeNestedArray()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SalesChannel/Api/StructEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SalesChannel\\Api\\StructEncoder::encodeStruct()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SalesChannel/Api/StructEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SalesChannel\\Api\\StructEncoder::loop()" is 27, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SalesChannel/Api/StructEncoder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SalesChannel\\Context\\SalesChannelContextService::get()" is 29, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SalesChannel/Context/SalesChannelContextService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SalesChannel\\Entity\\SalesChannelRepository::processCriteria()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SalesChannel/Entity/SalesChannelRepository.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\Snippet\\Command\\ValidateSnippetsCommand::execute()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/Snippet/Command/ValidateSnippetsCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\Snippet\\Files\\SnippetFileLoader::loadShippedSnippets()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/Snippet/Files/SnippetFileLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\Snippet\\SnippetValidator::findMissingSnippets()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/Snippet/SnippetValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\StateMachine\\StateMachineRegistry::getTransitionDestinationById()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/StateMachine/StateMachineRegistry.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\StateMachine\\StateMachineTransitionValidator::preValidate()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/StateMachine/StateMachineTransitionValidator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SystemConfig\\Service\\ConfigurationService::getSystemConfigDefinition()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SystemConfig/Service/ConfigurationService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 87, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SystemConfig/SystemConfigService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\System\\SystemConfig\\SystemConfigService::setMultiple()" is 26, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/System/SystemConfig/SystemConfigService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\Test\\Stub\\Redis\\RedisStub::set()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/Test/Stub/Redis/RedisStub.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Core\\TestBootstrapper::addPluginAutoloadDev()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/TestBootstrapper.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\TestBootstrapper should not depend on Shopware\\Core\\Framework\\Test\\TestCaseBase\\KernelLifecycleManager',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 6,
    'path' => __DIR__ . '/src/Core/TestBootstrapper.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\TestBootstrapper should not depend on Shopware\\Core\\Test\\PHPUnit\\CompletionGuard\\CompletionGuard',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/TestBootstrapper.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Core\\TestBootstrapper should not depend on Shopware\\Core\\Test\\PHPUnit\\EventHook\\ResetEventHooksSubscriber',
    'identifier' => 'phpat.productionCodeShouldNotDependOnTestCode',
    'count' => 1,
    'path' => __DIR__ . '/src/Core/TestBootstrapper.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Admin\\Indexer\\MediaAdminSearchIndexer::fetch()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Admin/Indexer/MediaAdminSearchIndexer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Admin\\Indexer\\OrderAdminSearchIndexer::fetch()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Admin/Indexer/OrderAdminSearchIndexer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 106, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Admin/Indexer/ProductAdminSearchIndexer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Admin\\Indexer\\ProductAdminSearchIndexer::mapAdvancedRows()" is 67, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Admin/Indexer/ProductAdminSearchIndexer.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 185, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Framework/DataAbstractionLayer/CriteriaParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Framework\\DataAbstractionLayer\\CriteriaParser::parseEqualsFilter()" is 24, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Framework/DataAbstractionLayer/CriteriaParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Framework\\DataAbstractionLayer\\CriteriaParser::parseFilterAggregation()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Framework/DataAbstractionLayer/CriteriaParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Framework\\Indexing\\IndexMappingUpdater::update()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Framework/Indexing/IndexMappingUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Product\\CustomFieldUpdater::customFieldsUpdated()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Product/CustomFieldUpdater.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Product\\ElasticsearchProductDefinition::fetch()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Product/ElasticsearchProductDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Product\\ElasticsearchProductDefinition::mapCustomFields()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Product/ElasticsearchProductDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Product\\LanguageSubscriber::onLanguageWritten()" is 25, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Product/LanguageSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Elasticsearch\\Product\\ProductCriteriaParser::parseFilter()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Elasticsearch/Product/ProductCriteriaParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Checkout\\Customer\\CustomerGroupSubscriber::createUrls()" is 27, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Checkout/Customer/CustomerGroupSubscriber.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Shopware\\Storefront\\Controller\\RegisterController should not depend on Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityRepository',
    'identifier' => 'phpat.storefrontControllersShouldNotAccessStorageDirectly',
    'count' => 4,
    'path' => __DIR__ . '/src/Storefront/Controller/RegisterController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Controller\\StorefrontController::addCartErrors()" is 34, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Controller/StorefrontController.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\DependencyInjection\\TwigComponentBundlePass::process()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/DependencyInjection/TwigComponentBundlePass.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Framework\\Seo\\App\\SeoUrlAppFeatureDefinition::validate()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Framework/Seo/App/SeoUrlAppFeatureDefinition.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Migration\\V6_3\\Migration1565640170ThemeMigrateMedia::update()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Migration/V6_3/Migration1565640170ThemeMigrateMedia.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Page\\Robots\\Parser\\RobotsDirectiveParser::parse()" is 36, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Page/Robots/Parser/RobotsDirectiveParser.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Page\\Robots\\RobotsPageLoader::selectDomainsByHostname()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Page/Robots/RobotsPageLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\Command\\ThemeChangeCommand::execute()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/Command/ThemeChangeCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\Command\\ThemeCompileCommand::execute()" is 18, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/Command/ThemeCompileCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\Command\\ThemeCreateCommand::execute()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/Command/ThemeCreateCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\Command\\ThemeDumpCommand::execute()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/Command/ThemeDumpCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\Command\\ThemeMigrateTranslationsCommand::execute()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/Command/ThemeMigrateTranslationsCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\Command\\ThemePrepareIconsCommand::execute()" is 37, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/Command/ThemePrepareIconsCommand.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\ConfigLoader\\DatabaseConfigLoader::resolveMediaIds()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ConfigLoader/DatabaseConfigLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\DatabaseSalesChannelThemeLoader::readFromDB()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/DatabaseSalesChannelThemeLoader.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\Snippet\\ThemeConfigSnippetGenerator::collectTranslations()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/Snippet/ThemeConfigSnippetGenerator.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 147, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeCompiler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\ThemeCompiler::dumpVariables()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeCompiler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\ThemeCompiler::readBundleComponentManifest()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeCompiler.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\ThemeConfigValueAccessor::getCssVarValues()" is 30, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeConfigValueAccessor.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 119, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeLifecycleService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\ThemeLifecycleService::updateMediaInConfiguration()" is 66, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeLifecycleService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 107, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeMergedConfigBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\ThemeMergedConfigBuilder::getPlainThemeConfiguration()" is 46, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeMergedConfigBuilder.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\ThemeService::updateTheme()" is 17, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/ThemeService.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Storefront\\Theme\\UnusedThemeDirectoryDeleter::deleteUnusedDirectories()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/src/Storefront/Theme/UnusedThemeDirectoryDeleter.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\DevOps\\Core\\DevOps\\ADRValidationTest::testADRValidation()" is 20, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/devops/Core/DevOps/ADRValidationTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Core\\Checkout\\Cart\\Order\\RecalculationServiceTest::testPersistOrderAndConvertToCart()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Core/Checkout/Cart/Order/RecalculationServiceTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 128, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Core/Framework/ApiRoutesHaveASchemaTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Core\\Framework\\ApiRoutesHaveASchemaTest::checkQueryParameters()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Core/Framework/ApiRoutesHaveASchemaTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Core\\Framework\\ApiRoutesHaveASchemaTest::testSchemaPathFilesDoNotDeclareDuplicateOperations()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Core/Framework/ApiRoutesHaveASchemaTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Core\\Framework\\ApiRoutesHaveASchemaTest::testStoreApiRoutesHaveASchema()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Core/Framework/ApiRoutesHaveASchemaTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Core\\System\\CustomEntity\\CustomEntityTest::testDefinition()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Core/System/CustomEntity/CustomEntityTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Core\\System\\UsageData\\Services\\EntityDefinitionServiceTest::testTaggedEntitiesOnlyHaveSinglePrimaryKey()" is 23, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Core/System/UsageData/Services/EntityDefinitionServiceTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 88, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Elasticsearch/Product/ElasticsearchProductTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Storefront\\Controller\\ProductControllerTest::testVariantGrayedOut()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Storefront/Controller/ProductControllerTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Integration\\Storefront\\Framework\\Routing\\RequestTransformerTest::createSalesChannels()" is 22, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/integration/Storefront/Framework/Routing/RequestTransformerTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Unit\\Core\\Checkout\\Promotion\\Cart\\ExclusionTraitUser::isExcluded()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/unit/Core/Checkout/Promotion/Cart/PromotionExclusionTraitTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Unit\\Core\\Framework\\Adapter\\Cache\\Http\\CacheHeadersServiceTest::testGenerateCashHashWithItemsInCart()" is 19, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/unit/Core/Framework/Adapter/Cache/Http/CacheHeadersServiceTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Class cognitive complexity is 97, keep it under 80',
    'identifier' => 'complexity.classLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/unit/Core/Framework/Api/ApiDefinition/Generator/StoreApiGeneratorTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Unit\\Core\\Framework\\Api\\ApiDefinition\\Generator\\StoreApiGeneratorTest::testAssociationDocumentationSkipsNonReadOperations()" is 16, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/unit/Core/Framework/Api/ApiDefinition/Generator/StoreApiGeneratorTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Unit\\Core\\Framework\\Api\\ApiDefinition\\Generator\\StoreApiGeneratorTest::testSwLanguageIdIsInjectedIntoEveryNonDeleteOperationOutsideInfo()" is 21, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/unit/Core/Framework/Api/ApiDefinition/Generator/StoreApiGeneratorTest.php',
];
$ignoreErrors[] = [
    'rawMessage' => 'Cognitive complexity for "Shopware\\Tests\\Unit\\Core\\Framework\\Demodata\\Generator\\ProductGeneratorTest::testProductGeneration()" is 48, keep it under 15',
    'identifier' => 'complexity.functionLike',
    'count' => 1,
    'path' => __DIR__ . '/tests/unit/Core/Framework/Demodata/Generator/ProductGeneratorTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
