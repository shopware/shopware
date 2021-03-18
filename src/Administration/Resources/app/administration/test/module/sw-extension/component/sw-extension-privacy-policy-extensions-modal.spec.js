import { shallowMount } from '@vue/test-utils';

import 'src/app/component/base/sw-button';
import 'src/module/sw-extension/component/sw-extension-privacy-policy-extensions-modal';


function createWrapper(props) {
    return shallowMount(Shopware.Component.build('sw-extension-privacy-policy-extensions-modal'), {
        propsData: {
            ...props
        },
        mocks: {
            $tc: (path, choice, values) => {
                if (values) {
                    return JSON.stringify({ path, choice, values });
                }

                return path;
            }
        },
        stubs: {
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-modal': {
                template: '<div class="sw-modal"><p class="title">{{ title }}</p><slot></slot><slot name="modal-footer"></slot></div>',
                props: ['title']
            }
        }
    });
}

describe('src/module/sw-extension/component/sw-extension-privacy-policy-extensions-modal', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', () => {
        wrapper = createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test'
        });


        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the values', () => {
        wrapper = createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test'

        });
        expect(wrapper.find('.sw-extension-privacy-policy-extensions-modal__text').text()).toBe('a privacy notice');
        expect(wrapper.find('.title').text()).toBe(JSON.stringify({
            path: 'sw-extension-store.component.sw-extension-privacy-policy-extensions-modal.title',
            choice: 0,
            values: {
                extensionLabel: 'Tes11Test'
            }
        }));

        expect(wrapper.find('.sw-extension-privacy-policy-extensions-modal__close-button').text()).toBe('global.default.confirm');
    });

    it('should close the modal', async () => {
        wrapper = createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test'

        });
        expect(wrapper.emitted()).toEqual({});

        wrapper.find('.sw-extension-privacy-policy-extensions-modal__close-button').trigger('click');

        await wrapper.vm.$nextTick();


        expect(wrapper.emitted()).toEqual({
            'modal-close': [[]]
        });
    });
});
