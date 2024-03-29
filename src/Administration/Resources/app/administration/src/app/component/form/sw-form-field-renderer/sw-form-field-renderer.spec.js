/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-form-field-renderer', {
        sync: true,
    }), {
        props: {
            config: { name: 'field2', type: 'text', config: { label: 'field2Label' } },
            value: 'data value',
        },
        global: {
            stubs: {
                'sw-text-field': {
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
    });
}

describe('components/form/sw-form-field-renderer', () => {
    beforeAll(() => {
        global.repositoryFactoryMock.showError = false;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
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
                config: { name: 'field2', type: 'text', config: { label: 'field2Label' } },
                value: 'data value',
                error: new ShopwareError({ code: 'dummyCode' }),
            },
        });

        expect(wrapper.props().error).toBeInstanceOf(ShopwareError);
    });
});
