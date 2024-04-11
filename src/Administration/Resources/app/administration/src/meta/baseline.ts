/**
 * @package admin
 */

import positionIdentifiers from './position-identifiers.json';
import dataSetIds from './data-sets.json';

/* eslint-disable max-len */
const missingTests = [
    'src/app/adapter/view/sw-vue-devtools.ts',
    'src/app/asyncComponent/media/sw-media-add-thumbnail-form/index.js',
    'src/app/asyncComponent/media/sw-media-entity-mapper/index.js',
    'src/app/asyncComponent/media/sw-media-folder-content/index.js',
    'src/app/asyncComponent/media/sw-media-list-selection-item-v2/index.js',
    'src/app/asyncComponent/media/sw-media-modal-folder-dissolve/index.js',
    'src/app/asyncComponent/media/sw-media-modal-replace/index.js',
    'src/app/asyncComponent/media/sw-media-replace/index.js',
    'src/app/asyncComponent/media/sw-media-url-form/index.js',
    'src/app/asyncComponent/media/sw-sidebar-media-item/index.js',
    'src/app/component/app/sw-app-actions/_fixtures/app-action.fixtures.js',
    'src/app/component/base/sw-button-group/index.js',
    'src/app/component/base/sw-button-process/index.js',
    'src/app/component/base/sw-card-filter/index.js',
    'src/app/component/base/sw-card-section/index.js',
    'src/app/component/base/sw-collapse/index.js',
    'src/app/component/base/sw-container/index.js',
    'src/app/component/base/sw-description-list/index.js',
    'src/app/component/base/sw-help-text/index.js',
    'src/app/component/base/sw-highlight-text/index.js',
    'src/app/component/base/sw-inheritance-switch/index.js',
    'src/app/component/base/sw-price-preview/index.js',
    'src/app/component/base/sw-radio-panel/index.js',
    'src/app/component/base/sw-sorting-select/index.js',
    'src/app/component/base/sw-user-card/index.js',
    'src/app/component/components.js',
    'src/app/component/context-menu/sw-context-menu/index.js',
    'src/app/component/context-menu/sw-context-menu-divider/index.js',
    'src/app/component/context-menu/sw-context-menu-item/index.js',
    'src/app/component/data-grid/sw-data-grid-column-boolean/index.js',
    'src/app/component/data-grid/sw-data-grid-column-position/index.js',
    'src/app/component/data-grid/sw-data-grid-inline-edit/index.js',
    'src/app/component/data-grid/sw-data-grid-skeleton/index.js',
    'src/app/component/filter/sw-sidebar-filter-panel/index.js',
    'src/app/component/form/field-base/sw-base-field/index.js',
    'src/app/component/form/field-base/sw-block-field/index.js',
    'src/app/component/form/field-base/sw-contextual-field/index.js',
    'src/app/component/form/select/base/sw-select-result-list/index.js',
    'src/app/component/form/select/entity/sw-entity-tag-select/index.js',
    'src/app/component/form/sw-boolean-radio-group/index.js',
    'src/app/component/form/sw-compact-colorpicker/index.js',
    'src/app/component/form/sw-confirm-field/index.js',
    'src/app/component/form/sw-email-field-deprecated/index.js',
    'src/app/component/form/sw-field-copyable/index.js',
    'src/app/component/form/sw-gtc-checkbox/index.js',
    'src/app/component/form/sw-select-field-deprecated/index.js',
    'src/app/component/form/sw-select-option/index.js',
    'src/app/component/form/sw-tagged-field/index.js',
    'src/app/component/form/sw-text-editor/sw-text-editor-table-toolbar/index.js',
    'src/app/component/form/sw-text-editor/sw-text-editor-toolbar/index.js',
    'src/app/component/form/sw-text-editor/sw-text-editor-toolbar-button/index.js',
    'src/app/component/form/sw-text-editor/sw-text-editor-toolbar-table-button/index.js',
    'src/app/component/form/sw-textarea-field-deprecated/index.js',
    'src/app/component/grid/sw-grid/index.js',
    'src/app/component/grid/sw-grid-column/index.js',
    'src/app/component/grid/sw-grid-row/index.js',
    'src/app/component/rule/condition-type/sw-condition-billing-zip-code/index.js',
    'src/app/component/rule/condition-type/sw-condition-customer-custom-field/index.js',
    'src/app/component/rule/condition-type/sw-condition-generic-line-item/index.js',
    'src/app/component/rule/condition-type/sw-condition-goods-count/index.js',
    'src/app/component/rule/condition-type/sw-condition-goods-price/index.js',
    'src/app/component/rule/condition-type/sw-condition-is-always-valid/index.js',
    'src/app/component/rule/condition-type/sw-condition-line-item/index.js',
    'src/app/component/rule/condition-type/sw-condition-line-item-goods-total/index.js',
    'src/app/component/rule/condition-type/sw-condition-line-item-in-category/index.js',
    'src/app/component/rule/condition-type/sw-condition-line-item-property/index.js',
    'src/app/component/rule/condition-type/sw-condition-line-item-purchase-price/index.js',
    'src/app/component/rule/condition-type/sw-condition-line-item-with-quantity/index.js',
    'src/app/component/rule/condition-type/sw-condition-not-found/index.js',
    'src/app/component/rule/condition-type/sw-condition-time-range/index.js',
    'src/app/component/rule/sw-condition-base-line-item/index.js',
    'src/app/component/rule/sw-condition-is-net-select/index.js',
    'src/app/component/rule/sw-condition-modal/index.js',
    'src/app/component/sidebar/sw-sidebar/index.js',
    'src/app/component/sidebar/sw-sidebar-item/index.js',
    'src/app/component/sidebar/sw-sidebar-navigation-item/index.js',
    'src/app/component/structure/sw-admin-menu/_sw-admin-menu-item/catalogues.js',
    'src/app/component/structure/sw-admin-menu-item/_sw-admin-menu-item/catalogues.js',
    'src/app/component/structure/sw-discard-changes-modal/index.js',
    'src/app/component/structure/sw-error/index.js',
    'src/app/component/structure/sw-hidden-iframes/index.js',
    'src/app/component/structure/sw-inheritance-warning/index.js',
    'src/app/component/structure/sw-modals-renderer/index.ts',
    'src/app/component/structure/sw-sales-channel-config/index.js',
    'src/app/component/structure/sw-sales-channel-switch/index.js',
    'src/app/component/structure/sw-search-more-results/index.js',
    'src/app/component/tree/sw-tree/fixtures/treeItems.js',
    'src/app/component/tree/sw-tree-input-field/index.js',
    'src/app/component/utils/sw-color-badge/index.js',
    'src/app/component/utils/sw-ignore-class/index.js',
    'src/app/component/utils/sw-license-violation/index.js',
    'src/app/component/utils/sw-notification-center-item/index.js',
    'src/app/component/utils/sw-notifications/index.js',
    'src/app/component/utils/sw-overlay/index.js',
    'src/app/component/utils/sw-popover-deprecated/index.js',
    'src/app/component/utils/sw-progress-bar/index.js',
    'src/app/component/utils/sw-skeleton/index.ts',
    'src/app/component/utils/sw-skeleton-bar-deprecated/index.ts',
    'src/app/component/utils/sw-step-display/index.js',
    'src/app/component/utils/sw-step-item/index.js',
    'src/app/component/utils/sw-text-preview/index.js',
    'src/app/component/utils/sw-upload-listener/index.js',
    'src/app/component/utils/sw-verify-user-modal/index.js',
    'src/app/component/utils/sw-vnode-renderer/index.js',
    'src/app/component/wizard/sw-wizard-dot-navigation/index.js',
    'src/app/component/wizard/sw-wizard-page/index.js',
    'src/app/decorator/index.js',
    'src/app/directive/index.ts',
    'src/app/directive/popover.directive.ts',
    'src/app/directive/tooltip.directive.ts',
    'src/app/filter/index.ts',
    'src/app/init/directive.init.ts',
    'src/app/init/index.ts',
    'src/app/init/login.init.js',
    'src/app/init/window.init.ts',
    'src/app/mixin/index.js',
    'src/app/plugin/device-helper.plugin.js',
    'src/app/plugin/index.ts',
    'src/app/plugin/sanitize.plugin.js',
    'src/app/route/index.js',
    'src/app/service/feature.service.ts',
    'src/app/service/language-auto-fetching.service.js',
    'src/app/service/license-violations.service.js',
    'src/app/service/locale-to-language.service.js',
    'src/app/service/search-type.service.js',
    'src/app/service/shortcut.service.js',
    'src/app/state/action-button.store.ts',
    'src/app/state/admin-menu.store.js',
    'src/app/state/context.store.ts',
    'src/app/state/extension-component-sections.store.ts',
    'src/app/state/extension-entry-routes.js',
    'src/app/state/extension-sdk-module.store.ts',
    'src/app/state/extensions.store.ts',
    'src/app/state/index.js',
    'src/app/state/license-violation.store.js',
    'src/app/state/main-module.store.ts',
    'src/app/state/menu-item.store.ts',
    'src/app/state/modals.store.ts',
    'src/app/state/notification.store.js',
    'src/app/state/rule-conditions-config.store.js',
    'src/app/state/sdk-location.store.ts',
    'src/app/state/settings-item.store.js',
    'src/app/state/shopware-apps.store.ts',
    'src/app/state/system.store.js',
    'src/app/state/tabs.store.ts',
    'src/core/adapter/view.adapter.ts',
    'src/core/data/ShopwareError.js',
    'src/core/data/changeset-generator.data.js',
    'src/core/data/criteria.data.ts',
    'src/core/data/entity-definition.data.ts',
    'src/core/data/entity-factory.data.js',
    'src/core/data/entity-hydrator.data.ts',
    'src/core/data/entity.data.ts',
    'src/core/data/error-codes/login.error-codes.js',
    'src/core/data/error-store.data.js',
    'src/core/data/filter-factory.data.js',
    'src/core/data/index.js',
    'src/core/data/repository-factory.data.ts',
    'src/core/factory/api-context.factory.js',
    'src/core/factory/api-service.factory.js',
    'src/core/factory/app-context.factory.js',
    'src/core/factory/classes-factory.js',
    'src/core/factory/directive.factory.ts',
    'src/core/factory/entity-definition.factory.js',
    'src/core/factory/filter.factory.ts',
    'src/core/factory/locale.factory.ts',
    'src/core/factory/mixin.factory.ts',
    'src/core/factory/plugin-boot.factory.js',
    'src/core/factory/router.factory.js',
    'src/core/factory/service.factory.ts',
    'src/core/factory/shortcut.factory.js',
    'src/core/factory/state-deprecated.factory.js',
    'src/core/factory/state.factory.ts',
    'src/core/factory/template.factory.js',
    'src/core/feature.ts',
    'src/core/helper/device.helper.js',
    'src/core/helper/middleware.helper.js',
    'src/core/helper/refresh-token.helper.js',
    'src/core/helper/store-loader.helper.js',
    'src/core/helper/upload-task.helper.js',
    'src/core/service/api/acl.api.service.js',
    'src/core/service/api/app-cms-blocks.service.js',
    'src/core/service/api/business-events.api.service.js',
    'src/core/service/api/cache.api.service.js',
    'src/core/service/api/checkout-store.api.service.ts',
    'src/core/service/api/config.api.service.ts',
    'src/core/service/api/customer-group-registration.api.service.js',
    'src/core/service/api/customer-validation.api.service.js',
    'src/core/service/api/excludedSearchTerm.api.service.js',
    'src/core/service/api/first-run-wizard.api.service.js',
    'src/core/service/api/flow-actions.api.service.js',
    'src/core/service/api/import-export.api.service.js',
    'src/core/service/api/index.ts',
    'src/core/service/api/integration.api.service.js',
    'src/core/service/api/known-ips.api.service.js',
    'src/core/service/api/language-plugin.api.service.js',
    'src/core/service/api/mail.api.service.js',
    'src/core/service/api/media-folder.api.service.js',
    'src/core/service/api/message-queue.api.service.ts',
    'src/core/service/api/number-range.api.service.js',
    'src/core/service/api/order-state-machine.api.service.ts',
    'src/core/service/api/order.api.service.js',
    'src/core/service/api/product-export.api.service.js',
    'src/core/service/api/product-stream-preview.service.js',
    'src/core/service/api/recommendations.api.service.js',
    'src/core/service/api/sales-channel.api.service.js',
    'src/core/service/api/scheduled-task.api.service.ts',
    'src/core/service/api/seo-url-template.api.service.js',
    'src/core/service/api/seo-url.api.service.js',
    'src/core/service/api/snippet-set.api.service.js',
    'src/core/service/api/snippet.api.service.js',
    'src/core/service/api/state-machine.api.service.js',
    'src/core/service/api/store-context.api.service.ts',
    'src/core/service/api/sync.api.service.js',
    'src/core/service/api/user-config.api.service.js',
    'src/core/service/api/user-input-sanitize.service.js',
    'src/core/service/api/user-recovery.api.service.js',
    'src/core/service/api/user-validation.api.service.js',
    'src/core/service/api/user.api.service.js',
    'src/core/service/customer-group-registration-listener.service.js',
    'src/core/service/entity-mapping.service.js',
    'src/core/service/shopware-updates-listener.service.js',
    'src/core/service/utils/debug.utils.ts',
    'src/core/service/utils/object.utils.ts',
    'src/core/service/utils/sort.utils.ts',
    'src/core/service/validation.service.js',
    'src/core/worker/admin-notification-worker.js',
    'src/core/worker/worker-notification-listener.js',
    'src/core/worker/admin-worker.worker.js',
    'src/core/worker/admin-worker.shared-worker.js',
    'src/global.types.ts',
    'src/module/index.js',
    'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-description/index.js',
    'src/module/sw-bulk-edit/component/sw-bulk-edit-form-field-renderer/index.js',
    'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-confirm/index.js',
    'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-error/index.js',
    'src/module/sw-bulk-edit/index.js',
    'src/module/sw-bulk-edit/init/services.init.js',
    'src/module/sw-bulk-edit/service/handler/bulk-edit-base.handler.js',
    'src/module/sw-bulk-edit/service/handler/bulk-edit-customer.handler.js',
    'src/module/sw-bulk-edit/service/handler/bulk-edit-order.handler.js',
    'src/module/sw-category/component/sw-category-entry-point-overwrite-modal/index.js',
    'src/module/sw-category/component/sw-category-sales-channel-multi-select/index.js',
    'src/module/sw-category/component/sw-landing-page-view/index.js',
    'src/module/sw-category/default-search-configuration.js',
    'src/module/sw-category/index.js',
    'src/module/sw-category/view/sw-category-detail-cms/index.js',
    'src/module/sw-category/view/sw-category-detail-seo/index.js',
    'src/module/sw-category/view/sw-landing-page-detail-cms/index.js',
    'src/module/sw-cms/index.ts',
    'src/module/sw-cms/default-search-configuration.ts',
    'src/module/sw-cms/shared/CmsVisibility.ts',
    'src/module/sw-cms/shared/MediaUploadResult.ts',
    'src/module/sw-cms/test-utils/index.js',
    'src/module/sw-cms/constant/sw-cms.constant.ts',
    'src/module/sw-cms/blocks/index.ts',
    'src/module/sw-cms/component/index.ts',
    'src/module/sw-cms/component/sw-cms-mapping-field/index.ts',
    'src/module/sw-cms/component/sw-cms-page-select/index.ts',
    'src/module/sw-cms/component/sw-cms-product-box-preview/index.ts',
    'src/module/sw-cms/component/sw-cms-section/sw-cms-section-config/index.ts',
    'src/module/sw-cms/component/sw-cms-sidebar/sw-cms-sidebar-nav-element/index.ts',
    'src/module/sw-cms/component/sw-cms-stage-add-block/index.ts',
    'src/module/sw-cms/component/sw-cms-stage-section-selection/index.ts',
    'src/module/sw-cms/component/sw-cms-toolbar/index.ts',
    'src/module/sw-cms/elements/index.ts',
    'src/module/sw-cms/elements/location-renderer/index.ts',
    'src/module/sw-cms/elements/form/component/templates/form-contact/index.js',
    'src/module/sw-cms/elements/form/component/templates/form-newsletter/index.js',
    'src/module/sw-customer/component/sw-customer-address-form-options/index.js',
    'src/module/sw-customer/constant/sw-customer.constant.js',
    'src/module/sw-customer/default-search-configuration.js',
    'src/module/sw-customer/index.js',
    'src/module/sw-dashboard/index.ts',
    'src/module/sw-extension/index.js',
    'src/module/sw-extension/mixin/sw-extension-error.mixin.js',
    'src/module/sw-extension/page/sw-extension-my-extensions-recommendation/index.js',
    'src/module/sw-extension/service/extension-error-handler.service.ts',
    'src/module/sw-extension/service/extension-error.service.js',
    'src/module/sw-extension/service/extension-store-action.service.ts',
    'src/module/sw-extension/service/index.ts',
    'src/module/sw-extension/store/extensions.store.ts',
    'src/module/sw-extension/store/index.ts',
    'src/module/sw-extension-sdk/index.js',
    'src/module/sw-first-run-wizard/index.js',
    'src/module/sw-first-run-wizard/page/index/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-data-import/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-defaults/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-finish/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-mailer-base/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-mailer-local/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-paypal-base/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-paypal-credentials/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-shopware-account/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-shopware-base/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-shopware-domain/index.js',
    'src/module/sw-first-run-wizard/view/sw-first-run-wizard-store/index.js',
    'src/module/sw-flow/component/modals/sw-flow-leave-page-modal/index.js',
    'src/module/sw-flow/component/sw-flow-sequence-modal/index.js',
    'src/module/sw-flow/constant/flow.constant.js',
    'src/module/sw-flow/index.js',
    'src/module/sw-flow/state/flow.state.js',
    'src/module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard/index.js',
    'src/module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard-csv-page/index.js',
    'src/module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard-general-page/index.js',
    'src/module/sw-import-export/component/profile-wizard/sw-import-export-new-profile-wizard-mapping-page/index.js',
    'src/module/sw-import-export/component/sw-import-export-edit-profile-field-indicators/index.js',
    'src/module/sw-import-export/index.js',
    'src/module/sw-import-export/page/sw-import-export/index.js',
    'src/module/sw-import-export/service/mocks/mappings.mock.js',
    'src/module/sw-import-export/view/sw-import-export-view-export/index.js',
    'src/module/sw-import-export/view/sw-import-export-view-import/index.js',
    'src/module/sw-integration/index.js',
    'src/module/sw-landing-page/default-search-configuration.js',
    'src/module/sw-landing-page/index.js',
    'src/module/sw-login/index.js',
    'src/module/sw-mail-template/index.js',
    'src/module/sw-mail-template/page/sw-mail-header-footer-create/index.js',
    'src/module/sw-mail-template/page/sw-mail-template-create/index.js',
    'src/module/sw-manufacturer/default-search-configuration.js',
    'src/module/sw-manufacturer/index.js',
    'src/module/sw-media/component/sidebar/sw-media-quickinfo-metadata-item/index.js',
    'src/module/sw-media/component/sidebar/sw-media-quickinfo-multiple/index.js',
    'src/module/sw-media/component/sidebar/sw-media-tag/index.js',
    'src/module/sw-media/component/sw-media-breadcrumbs/index.js',
    'src/module/sw-media/component/sw-media-collapse/index.js',
    'src/module/sw-media/component/sw-media-display-options/index.js',
    'src/module/sw-media/component/sw-media-grid/index.js',
    'src/module/sw-media/default-search-configuration.js',
    'src/module/sw-media/index.js',
    'src/module/sw-media/mixin/media-grid-listener.mixin.js',
    'src/module/sw-media/mixin/media-sidebar-modal.mixin.js',
    'src/module/sw-newsletter-recipient/component/sw-newsletter-recipient-filter-switch/index.js',
    'src/module/sw-newsletter-recipient/default-search-configuration.js',
    'src/module/sw-newsletter-recipient/index.js',
    'src/module/sw-order/component/sw-order-create-details-body/index.js',
    'src/module/sw-order/component/sw-order-create-details-header/index.js',
    'src/module/sw-order/component/sw-order-create-invalid-promotion-modal/index.js',
    'src/module/sw-order/component/sw-order-create-promotion-modal/index.js',
    'src/module/sw-order/component/sw-order-customer-comment/index.js',
    'src/module/sw-order/component/sw-order-document-settings-delivery-note-modal/index.js',
    'src/module/sw-order/component/sw-order-document-settings-invoice-modal/index.js',
    'src/module/sw-order/component/sw-order-inline-field/index.js',
    'src/module/sw-order/component/sw-order-leave-page-modal/index.js',
    'src/module/sw-order/component/sw-order-nested-line-items-row/index.js',
    'src/module/sw-order/component/sw-order-promotion-tag-field/index.js',
    'src/module/sw-order/component/sw-order-saveable-field/index.js',
    'src/module/sw-order/component/sw-order-state-change-modal/index.js',
    'src/module/sw-order/component/sw-order-state-change-modal/sw-order-state-change-modal-attach-documents/index.js',
    'src/module/sw-order/component/sw-order-state-history-card-entry/index.js',
    'src/module/sw-order/default-search-configuration.js',
    'src/module/sw-order/index.js',
    'src/module/sw-order/mixin/cart-notification.mixin.ts',
    'src/module/sw-order/order.types.ts',
    'src/module/sw-order/state/order.store.ts',
    'src/module/sw-order/view/sw-order-create-initial/index.js',
    'src/module/sw-order/view/sw-order-detail-documents/index.js',
    'src/module/sw-privilege-error/index.js',
    'src/module/sw-product/component/sw-product-basic-form/index.js',
    'src/module/sw-product/component/sw-product-packaging-form/index.js',
    'src/module/sw-product/component/sw-product-settings-form/index.js',
    'src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-restriction-selection/index.js',
    'src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-prices/index.js',
    'src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-price-field/index.js',
    'src/module/sw-product/component/sw-product-variants/sw-product-variants-delivery/sw-product-variants-delivery-listing/index.js',
    'src/module/sw-product/component/sw-product-visibility-select/index.js',
    'src/module/sw-product/default-search-configuration.js',
    'src/module/sw-product/index.js',
    'src/module/sw-product-stream/default-search-configuration.js',
    'src/module/sw-product-stream/index.js',
    'src/module/sw-product-stream/page/sw-product-stream-list/index.js',
    'src/module/sw-profile/extension/sw-admin-menu/index.js',
    'src/module/sw-profile/index.js',
    'src/module/sw-profile/state/sw-profile.state.js',
    'src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-discount-type/index.js',
    'src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-rule-selection/index.js',
    'src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-trigger/index.js',
    'src/module/sw-promotion-v2/component/discount/sw-promotion-v2-wizard-description/index.js',
    'src/module/sw-promotion-v2/component/discount/sw-promotion-v2-wizard-discount-selection/index.js',
    'src/module/sw-promotion-v2/component/sw-promotion-v2-sales-channel-select/index.js',
    'src/module/sw-promotion-v2/default-search-configuration.js',
    'src/module/sw-promotion-v2/helper/promotion.helper.js',
    'src/module/sw-promotion-v2/index.js',
    'src/module/sw-promotion-v2/init/services.init.js',
    'src/module/sw-promotion-v2/promotion.types.ts',
    'src/module/sw-promotion-v2/service/promotion-code.api.service.js',
    'src/module/sw-property/component/sw-property-detail-base/index.js',
    'src/module/sw-property/component/sw-property-option-detail/index.js',
    'src/module/sw-property/default-search-configuration.js',
    'src/module/sw-property/index.js',
    'src/module/sw-property/page/sw-property-create/index.js',
    'src/module/sw-review/index.js',
    'src/module/sw-sales-channel/component/sw-sales-channel-modal-detail/index.js',
    'src/module/sw-sales-channel/component/sw-sales-channel-modal-grid/index.js',
    'src/module/sw-sales-channel/default-search-configuration.js',
    'src/module/sw-sales-channel/index.js',
    'src/module/sw-sales-channel/page/sw-sales-channel-list/index.js',
    'src/module/sw-sales-channel/product-export-templates/billiger-de/index.js',
    'src/module/sw-sales-channel/product-export-templates/google-product-search-de/index.js',
    'src/module/sw-sales-channel/product-export-templates/idealo/index.js',
    'src/module/sw-sales-channel/product-export-templates/index.js',
    'src/module/sw-sales-channel/service/domain-link.service.js',
    'src/module/sw-sales-channel/service/export-template.service.js',
    'src/module/sw-sales-channel/view/sw-sales-channel-create-base/index.js',
    'src/module/sw-sales-channel/view/sw-sales-channel-detail-product-comparison-preview/index.js',
    'src/module/sw-settings/component/sw-settings-item/index.js',
    'src/module/sw-settings/mixin/sw-settings-list.mixin.js',
    'src/module/sw-settings-address/index.js',
    'src/module/sw-settings-address/page/sw-settings-address/index.js',
    'src/module/sw-settings-basic-information/component/sw-settings-captcha-select-v2/index.js',
    'src/module/sw-settings-basic-information/index.js',
    'src/module/sw-settings-basic-information/page/sw-settings-basic-information/index.js',
    'src/module/sw-settings-cache/component/sw-settings-cache-modal/index.js',
    'src/module/sw-settings-cache/index.js',
    'src/module/sw-settings-cart/index.js',
    'src/module/sw-settings-cart/page/sw-settings-cart/index.js',
    'src/module/sw-settings-country/index.js',
    'src/module/sw-settings-country/page/sw-settings-country-create/index.js',
    'src/module/sw-settings-currency/index.js',
    'src/module/sw-settings-custom-field/component/sw-custom-field-type-base/index.js',
    'src/module/sw-settings-custom-field/component/sw-custom-field-type-checkbox/index.js',
    'src/module/sw-settings-custom-field/component/sw-custom-field-type-date/index.js',
    'src/module/sw-settings-custom-field/component/sw-custom-field-type-number/index.js',
    'src/module/sw-settings-custom-field/component/sw-custom-field-type-text/index.js',
    'src/module/sw-settings-custom-field/component/sw-custom-field-type-text-editor/index.js',
    'src/module/sw-settings-custom-field/index.js',
    'src/module/sw-settings-customer-group/default-search-configuration.js',
    'src/module/sw-settings-customer-group/index.js',
    'src/module/sw-settings-delivery-times/index.js',
    'src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-create/index.js',
    'src/module/sw-settings-document/index.js',
    'src/module/sw-settings-language/index.js',
    'src/module/sw-settings-listing/component/sw-settings-listing-delete-modal/index.js',
    'src/module/sw-settings-listing/index.js',
    'src/module/sw-settings-logging/component/sw-settings-logging-entry-info/index.js',
    'src/module/sw-settings-logging/component/sw-settings-logging-mail-sent-info/index.js',
    'src/module/sw-settings-logging/index.js',
    'src/module/sw-settings-login-registration/index.js',
    'src/module/sw-settings-mailer/index.js',
    'src/module/sw-settings-newsletter/index.js',
    'src/module/sw-settings-number-range/index.js',
    'src/module/sw-settings-number-range/page/sw-settings-number-range-create/index.js',
    'src/module/sw-settings-payment/component/sw-plugin-box/index.js',
    'src/module/sw-settings-payment/default-search-configuration.js',
    'src/module/sw-settings-payment/index.js',
    'src/module/sw-settings-payment/init/index.ts',
    'src/module/sw-settings-payment/page/sw-settings-payment-create/index.js',
    'src/module/sw-settings-payment/state/overview-cards.store.ts',
    'src/module/sw-settings-product-feature-sets/index.js',
    'src/module/sw-settings-product-feature-sets/service/feature-grid-translation.service.js',
    'src/module/sw-settings-salutation/index.js',
    'src/module/sw-settings-search/index.js',
    'src/module/sw-settings-search/init/services.init.js',
    'src/module/sw-settings-search/service/livesearch.api.service.js',
    'src/module/sw-settings-search/service/productIndex.api.service.js',
    'src/module/sw-settings-search/view/sw-settings-search-view-general/index.js',
    'src/module/sw-settings-seo/component/sw-seo-url-template-card/index.js',
    'src/module/sw-settings-seo/index.js',
    'src/module/sw-settings-shipping/default-search-configuration.js',
    'src/module/sw-settings-shipping/index.js',
    'src/module/sw-settings-shopware-updates/index.js',
    'src/module/sw-settings-shopware-updates/page/sw-settings-shopware-updates-index/index.js',
    'src/module/sw-settings-shopware-updates/view/sw-settings-shopware-updates-info/index.js',
    'src/module/sw-settings-shopware-updates/view/sw-settings-shopware-updates-plugins/index.js',
    'src/module/sw-settings-shopware-updates/view/sw-settings-shopware-updates-requirements/index.js',
    'src/module/sw-settings-sitemap/index.js',
    'src/module/sw-settings-sitemap/page/sw-settings-sitemap/index.js',
    'src/module/sw-settings-snippet/index.js',
    'src/module/sw-settings-snippet/page/sw-settings-snippet-create/index.js',
    'src/module/sw-settings-store/index.js',
    'src/module/sw-settings-tag/index.js',
    'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code/index.js',
    'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-cell/index.js',
    'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-range/index.js',
    'src/module/sw-settings-tax/component/sw-settings-tax-rule-type-zip-code-range-cell/index.js',
    'src/module/sw-settings-tax/index.js',
    'src/module/sw-settings-units/index.js',
    'src/module/sw-settings-units/page/sw-settings-units-detail/index.ts',
    'src/module/sw-users-permissions/components/sw-users-permissions-detailed-additional-permissions/index.js',
    'src/module/sw-users-permissions/index.js',
    'src/app/component/form/select/entity/sw-entity-many-to-many-select/index.js',
    'src/module/sw-sales-channel/component/sw-sales-channel-modal/index.js',
    // Doubled vite files
    'src/app/main.vite.ts',
    'src/index.vite.ts',
    'src/app/init-post/index.vite.ts',
    'src/app/init-post/worker.init.vite.ts',
];

/**
 * @private
 */
export {
    missingTests,
    positionIdentifiers,
    dataSetIds,
};
