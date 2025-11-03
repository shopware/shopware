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
                    'sw-text-field-deprecated': {
                        template: '<div class="sw-text-field"><slot name="label"></slot><slot></slot></div>',
                    },
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
});
