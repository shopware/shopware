import ComponentFactory from 'src/core/factory/component.factory';
import overrideTemplate1 from 'module/core.product_extension/src/overrides/product_list_1.html.twig';
import overrideTemplate2 from 'module/core.product_extension/src/overrides/product_list_2.html.twig';

export default {
    initOverrides
};

function initOverrides() {
    ComponentFactory.override('core-product-list', {
        template: overrideTemplate1
    });

    ComponentFactory.override('core-product-list', {
        template: overrideTemplate2
    });
}
