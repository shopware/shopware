/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

async function createWrapper(additionalOptions = {}) {
    return mount(
        await wrapTestComponent('sw-form-field-renderer', {
            sync: true,
        }),
        {
            props: {
                config: {
                    name: 'field2',
                    type: 'text',
                    config: { label: 'field2Label' },
                },
                value: 'data value',
            },
            global: {
                stubs: {
                    'mt-text-field': {
                        template: '<div class="sw-text-field"><slot name="label"></slot><slot></slot></div>',
                    },
                    'sw-media-compact-upload-v2': true,
                    'sw-contextual-field': true,
                    'sw-block-field': true,
                    'sw-base-field': true,
                    'sw-field-error': true,
                },
                provide: {
                    validationService: {},
                    repositoryFactory: {
                        create() {
                            return {
                                get() {
                                    return Promise.resolve({});
                                },
                            };
                        },
                    },
                },
            },
            ...additionalOptions,
        },
    );
}

describe('components/form/sw-form-field-renderer', () => {
    beforeAll(() => {
        global.repositoryFactoryMock.showError = false;
    });

    it('should show the value from the label slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                label: '<template>Label from slot</template>',
            },
        });
        await flushPromises();
        const contentWrapper = wrapper.find('.sw-form-field-renderer');
        expect(contentWrapper.text()).toBe('Label from slot');
    });

    it('should show the value from the default slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>',
            },
        });
        const contentWrapper = wrapper.find('.sw-form-field-renderer');
        expect(contentWrapper.text()).toBe('I am in the default slot');
    });

    it('should has props error', async () => {
        const wrapper = await createWrapper({
            propsData: {
                config: {
                    name: 'field2',
                    type: 'text',
                    config: { label: 'field2Label' },
                },
                value: 'data value',
                error: new ShopwareError({ code: 'dummyCode' }),
            },
        });

        expect(wrapper.props().error).toBeInstanceOf(ShopwareError);
    });

    it('should init the current value when type is price without emit the update event', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'price',
                config: {
                    customFieldType: 'price',
                },
                value: undefined,
            },
        });

        expect(wrapper.vm.currentValue).toStrictEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    currencyId: null,
                    gross: null,
                    net: null,
                    linked: true,
                }),
            ]),
        );

        expect(wrapper.emitted('update:value')).toBeUndefined();
    });

    it('should enable multi selection for meteor multi-select fields', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'multi-select',
                config: {
                    options: [],
                },
                value: [],
            },
        });

        expect(wrapper.vm.bind.enableMultiSelection).toBe(true);
    });

    it('should emit the selected media id for sw-media-compact-upload-v2', async () => {
        const wrapper = await createWrapper({
            props: {
                config: {
                    name: 'companyLogoId',
                    componentName: 'sw-media-compact-upload-v2',
                },
                value: null,
            },
        });

        await wrapper.getComponent({ name: 'sw-media-compact-upload-v2' }).vm.$emit('selection-change', [{ id: 'media-id' }]);

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)).toEqual(['media-id']);
    });

    it('should emit null when sw-media-compact-upload-v2 removes the current image', async () => {
        const wrapper = await createWrapper({
            props: {
                config: {
                    name: 'companyLogoId',
                    componentName: 'sw-media-compact-upload-v2',
                },
                value: 'media-id',
            },
        });

        await wrapper.getComponent({ name: 'sw-media-compact-upload-v2' }).vm.$emit('media-upload-remove-image');

        expect(wrapper.emitted('update:value')).toBeTruthy();
        expect(wrapper.emitted('update:value').at(-1)).toEqual([null]);
    });
});
