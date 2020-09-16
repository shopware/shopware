import { shallowMount } from '@vue/test-utils';
// eslint-disable-next-line max-len
import 'src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-selection';
import 'src/app/component/base/sw-property-search';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-product-variants-configurator-selection'), {
        propsData: {
            options: [],
            product: {}
        },
        mocks: {
            $tc: () => {}
        },
        provide: {
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve() })
            },
            validationService: {}
        },
        stubs: {
            'sw-simple-search-field': Shopware.Component.build('sw-simple-search-field'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': {
                template: '<div></div>'
            }
        }
    });
}

describe('components/base/sw-product-variants-configurator-selection', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should keep the text content when search list opens', async () => {
        const inputField = wrapper.find('.sw-field input');

        // verify that input field is empty
        expect(inputField.element.value).toBe('');

        await inputField.setValue('15');

        expect(inputField.element.value).toBe('15');
    });
});
