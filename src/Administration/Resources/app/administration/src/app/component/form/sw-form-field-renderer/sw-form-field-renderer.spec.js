/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';
import 'src/app/component/form/sw-form-field-renderer';

async function createWrapper(additionalOptions = {}) {
    return shallowMount(await Shopware.Component.build('sw-form-field-renderer'), {
        stubs: {
            'sw-field': {
                template: '<div class="sw-field"><slot name="label"></slot><slot></slot></div>'
            },
            'sw-text-field': true,
            'sw-contextual-field': true,
            'sw-block-field': true,
            'sw-base-field': true,
            'sw-field-error': true,
        },
        propsData: {
            config: { name: 'field2', type: 'text', config: { label: 'field2Label' } },
            value: 'data value'
        },
        provide: {
            validationService: {},
            repositoryFactory: {
                create() {
                    return {
                        get() {
                            return Promise.resolve({});
                        }
                    };
                },
            },
        },
        ...additionalOptions
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
            scopedSlots: {
                label: '<template>Label from slot</template>'
            }
        });
        const contentWrapper = wrapper.find('.sw-form-field-renderer');
        expect(contentWrapper.text()).toEqual('Label from slot');
    });

    it('should show the value from the default slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>'
            }
        });
        const contentWrapper = wrapper.find('.sw-form-field-renderer');
        expect(contentWrapper.text()).toEqual('I am in the default slot');
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
