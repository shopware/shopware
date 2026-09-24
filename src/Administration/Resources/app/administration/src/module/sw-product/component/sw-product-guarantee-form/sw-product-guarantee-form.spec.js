/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

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
        store.product.id = 'productId';
        store.product.getEntityName = () => 'product';
        store.product.guaranteeMonths = 36;
        store.product.guaranteeConfirmed = false;
        store.product.manufacturer = { translated: { name: 'Manufacturer' } };
        store.product.manufacturerNumber = 'MPN-1';
        store.parentProduct = {};

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
        State.commit('error/resetApiErrors');
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

    it.each([
        '25',
        '31',
    ])('should keep the invalid duration %s instead of correcting it', async (guaranteeMonths) => {
        const monthsField = wrapper.find('.sw-field--number input');

        await monthsField.setValue(guaranteeMonths);
        await monthsField.trigger('change');

        expect(monthsField.element.value).toBe(guaranteeMonths);
        expect(store.product.guaranteeMonths).toBe(Number(guaranteeMonths));
    });

    it('should show the api validation error on the guarantee months field', async () => {
        State.commit('error/addApiError', {
            expression: 'product.productId.guaranteeMonths',
            error: new ShopwareError({
                code: 'INVALID_GARAN_GUARANTEE_MONTHS',
                detail: 'The GARAN guarantee duration must be empty or a half-year value greater than 24 months.',
            }),
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-field--number .sw-field__error').text()).toBe(
            'The GARAN guarantee duration must be empty or a half-year value greater than 24 months.',
        );
    });

    it('should disable the fields when allowEdit is false', async () => {
        wrapper = await createWrapper({ allowEdit: false }, ['product.editor']);

        const monthsField = wrapper.find('.sw-field--number input');
        const confirmedField = wrapper.find('.sw-field--switch input');

        expect(monthsField.element.disabled).toBe(true);
        expect(confirmedField.element.disabled).toBe(true);
    });

    describe('unmet label requirements notice', () => {
        it('should not be shown while the label is deactivated', async () => {
            store.product.guaranteeConfirmed = false;
            store.product.guaranteeMonths = null;
            store.product.manufacturer = null;
            store.product.manufacturerNumber = null;
            await flushPromises();

            expect(wrapper.find('.sw-product-guarantee-form__requirements-notice').exists()).toBe(false);
        });

        it('should not be shown while all requirements are met', async () => {
            store.product.guaranteeConfirmed = true;
            await flushPromises();

            expect(wrapper.find('.sw-product-guarantee-form__requirements-notice').exists()).toBe(false);
        });

        it.each([
            [
                'guarantee duration',
                { guaranteeMonths: 12 },
                'sw-product.settingsForm.noticeGuaranteeRequirementMonths',
            ],
            [
                'guarantee duration above 600 months',
                { guaranteeMonths: 606 },
                'sw-product.settingsForm.noticeGuaranteeRequirementMonths',
            ],
            [
                'manufacturer',
                { manufacturer: null },
                'sw-product.settingsForm.noticeGuaranteeRequirementManufacturer',
            ],
            [
                'manufacturer number',
                { manufacturerNumber: '  ' },
                'sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber',
            ],
        ])('should name the missing %s', async (_name, productOverride, snippet) => {
            store.product.guaranteeConfirmed = true;
            Object.assign(store.product, productOverride);
            await flushPromises();

            const requirements = wrapper.findAll('.sw-product-guarantee-form__requirements-notice li');

            expect(requirements).toHaveLength(1);
            expect(requirements.at(0).text()).toBe(snippet);
        });

        it('should name every missing requirement', async () => {
            store.product.guaranteeConfirmed = true;
            store.product.guaranteeMonths = null;
            store.product.manufacturer = null;
            store.product.manufacturerNumber = null;
            await flushPromises();

            expect(wrapper.findAll('.sw-product-guarantee-form__requirements-notice li').map((item) => item.text())).toEqual(
                [
                    'sw-product.settingsForm.noticeGuaranteeRequirementMonths',
                    'sw-product.settingsForm.noticeGuaranteeRequirementManufacturer',
                    'sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber',
                ],
            );
        });

        it('should resolve the requirements inherited from the parent product', async () => {
            store.product.guaranteeConfirmed = null;
            store.product.guaranteeMonths = null;
            store.product.manufacturer = null;
            store.product.manufacturerNumber = null;
            store.parentProduct = {
                id: 'parentId',
                guaranteeConfirmed: true,
                guaranteeMonths: 36,
                manufacturer: { translated: { name: 'Parent manufacturer' } },
                manufacturerNumber: 'MPN-PARENT',
            };
            await flushPromises();

            expect(wrapper.find('.sw-product-guarantee-form__requirements-notice').exists()).toBe(false);
        });

        it('should name the requirement a variant does not inherit either', async () => {
            store.product.guaranteeConfirmed = true;
            store.product.manufacturerNumber = null;
            store.parentProduct = {
                id: 'parentId',
                guaranteeMonths: 36,
                manufacturer: { translated: { name: 'Parent manufacturer' } },
                manufacturerNumber: null,
            };
            await flushPromises();

            const requirements = wrapper.findAll('.sw-product-guarantee-form__requirements-notice li');

            expect(requirements).toHaveLength(1);
            expect(requirements.at(0).text()).toBe('sw-product.settingsForm.noticeGuaranteeRequirementManufacturerNumber');
        });

        describe('after a successful save', () => {
            let scrollIntoView;

            beforeEach(() => {
                // jsdom does not implement scrollIntoView at all.
                scrollIntoView = jest.fn();
                Element.prototype.scrollIntoView = scrollIntoView;
            });

            afterEach(() => {
                delete Element.prototype.scrollIntoView;
            });

            it('should be scrolled into view', async () => {
                store.product.guaranteeConfirmed = true;
                store.product.manufacturerNumber = null;
                await flushPromises();

                Shopware.Utils.EventBus.emit('sw-product-detail-save-success');
                await flushPromises();

                expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'center' });
            });

            it('should not scroll anywhere while every requirement is met', async () => {
                store.product.guaranteeConfirmed = true;
                await flushPromises();

                Shopware.Utils.EventBus.emit('sw-product-detail-save-success');
                await flushPromises();

                expect(scrollIntoView).not.toHaveBeenCalled();
            });

            it('should stop listening once the form is gone', async () => {
                store.product.guaranteeConfirmed = true;
                store.product.manufacturerNumber = null;
                await flushPromises();

                wrapper.unmount();

                Shopware.Utils.EventBus.emit('sw-product-detail-save-success');
                await flushPromises();

                expect(scrollIntoView).not.toHaveBeenCalled();
            });
        });
    });
});
