/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const { State } = Shopware;

describe('src/module/sw-product/component/sw-product-guarantee-form', () => {
    let wrapper;
    let store;

    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: {},
                parentProduct: {},
            },
        });
    });

    async function createWrapper(propsOverride = {}, privileges = []) {
        store = State.get('swProductDetail');
        store.product.guaranteeMonths = 36;
        store.product.guaranteeConfirmed = false;

        const acl = {
            can: (privilege) => {
                if (!privilege) {
                    return true;
                }

                return privileges.includes(privilege);
            },
        };

        return mount(await wrapTestComponent('sw-product-guarantee-form', { sync: true }), {
            props: {
                allowEdit: true,
                ...propsOverride,
            },
            global: {
                stubs: {
                    'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                    'sw-number-field': await wrapTestComponent('sw-number-field', { sync: true }),
                    'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                    'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                    'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch', { sync: true }),
                    'sw-ai-copilot-badge': true,
                    'sw-field-copyable': true,
                    'sw-help-text': true,
                    'sw-icon': true,
                },
                provide: {
                    acl,
                },
            },
        });
    }

    beforeEach(async () => {
        wrapper = await createWrapper({}, ['product.editor']);
    });

    it('should render the guarantee months and confirmation fields with current values', async () => {
        const monthsField = wrapper.find('.sw-field--number input');
        const confirmedField = wrapper.find('.sw-field--switch input');

        expect(monthsField.element.value).toBe('36');
        expect(confirmedField.element.checked).toBe(false);
    });

    it('should write a changed guarantee duration back to the product', async () => {
        const monthsField = wrapper.find('.sw-field--number input');

        await monthsField.setValue('42');
        await monthsField.trigger('change');

        expect(store.product.guaranteeMonths).toBe(42);
    });

    it('should write the guarantee confirmation back to the product', async () => {
        const confirmedField = wrapper.find('.sw-field--switch input');

        await confirmedField.setValue(true);

        expect(store.product.guaranteeConfirmed).toBe(true);
    });

    it('should disable the fields when allowEdit is false', async () => {
        wrapper = await createWrapper({ allowEdit: false }, ['product.editor']);

        const monthsField = wrapper.find('.sw-field--number input');
        const confirmedField = wrapper.find('.sw-field--switch input');

        expect(monthsField.element.disabled).toBe(true);
        expect(confirmedField.element.disabled).toBe(true);
    });
});
