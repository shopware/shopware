import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';
import 'src/app/component/base/sw-property-assignment';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-select-rule-create';
import 'src/app/component/form/sw-snippet-field-edit-modal';
import 'src/app/component/media/sw-image-slider';
import 'src/app/component/media/sw-media-base-item';
import 'src/app/component/media/sw-media-folder-content';
import 'src/app/component/media/sw-media-list-selection-item-v2';
import 'src/app/component/rule/condition-type/sw-condition-cart-amount';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/structure/sw-admin-menu-item';
import 'src/app/component/structure/sw-search-bar-item';
import 'src/app/component/tree/sw-tree-item';
import 'src/module/sw-cms/component/sw-cms-layout-modal';
import 'src/module/sw-cms/component/sw-cms-section';
import 'src/module/sw-customer/view/sw-customer-detail-addresses';
import 'src/module/sw-extension/component/sw-extension-card-bought';
import 'src/module/sw-mail-template/page/sw-mail-template-detail';
import 'src/module/sw-extension/component/sw-extension-card-base';
import 'src/module/sw-order/component/sw-order-line-items-grid';
import 'src/module/sw-product-stream/page/sw-product-stream-detail';
import 'src/module/sw-sales-channel/view/sw-sales-channel-detail-base';
import 'src/module/sw-settings-country/page/sw-settings-country-list';
import 'src/module/sw-settings-search/component/sw-settings-search-searchable-content';
import 'src/module/sw-settings-document/page/sw-settings-document-list';
import 'src/module/sw-settings-payment/page/sw-settings-payment-list';
import 'src/module/sw-settings-number-range/page/sw-settings-number-range-list';

describe('the compiled template should stay the same even after removing duplicate blocks', () => {
    [
        'sw-property-assignment',
        'sw-context-menu-item',
        'sw-data-grid',
        'sw-select-rule-create',
        'sw-snippet-field-edit-modal',
        'sw-image-slider',
        'sw-media-base-item',
        'sw-media-folder-content',
        'sw-media-list-selection-item-v2',
        'sw-condition-cart-amount',
        'sw-admin-menu-item',
        'sw-search-bar-item',
        'sw-tree-item',
        'sw-cms-layout-modal',
        'sw-cms-section',
        'sw-customer-detail-addresses',
        'sw-extension-card-bought',
        'sw-mail-template-detail',
        'sw-order-line-items-grid',
        'sw-product-stream-detail',
        'sw-sales-channel-detail-base',
        'sw-settings-country-list',
        'sw-settings-document-list',
        'sw-settings-number-range-list',
        'sw-settings-payment-list',
        'sw-settings-search-searchable-content'
    ].forEach((componentName) => {
        it(componentName, async () => {
            const component = Shopware.Component.build(componentName);
            const componentTemplate = component?.template;

            expect(componentTemplate).toMatchSnapshot();
        });
    });
});
