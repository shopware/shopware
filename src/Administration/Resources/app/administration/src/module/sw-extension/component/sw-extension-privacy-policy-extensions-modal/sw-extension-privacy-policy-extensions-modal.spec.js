import { shallowMount } from '@vue/test-utils';

import 'src/app/component/base/sw-button';
import swExtensionPrivacyPolicyExtensionsModal from 'src/module/sw-extension/component/sw-extension-privacy-policy-extensions-modal';

Shopware.Component.register('sw-extension-privacy-policy-extensions-modal', swExtensionPrivacyPolicyExtensionsModal);

async function createWrapper(props) {
    return shallowMount(await Shopware.Component.build('sw-extension-privacy-policy-extensions-modal'), {
        propsData: {
            ...props,
        },
        mocks: {
            $tc: (path, choice, values) => {
                if (values) {
                    return JSON.stringify({ path, choice, values });
                }

                return path;
            },
        },
        stubs: {
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-modal': {
                // eslint-disable-next-line max-len
                template: '<div class="sw-modal"><p class="title">{{ title }}</p><slot></slot><slot name="modal-footer"></slot></div>',
                props: ['title'],
            },
        },
    });
}

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-extension-privacy-policy-extensions-modal', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test',
        });


        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the values', async () => {
        wrapper = await createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test',

        });
        expect(wrapper.find('.sw-extension-privacy-policy-extensions-modal__text')
            .text()).toBe('a privacy notice');
        expect(wrapper.find('.title').text()).toBe(JSON.stringify({
            path: 'sw-extension-store.component.sw-extension-privacy-policy-extensions-modal.title',
            choice: 0,
            values: {
                extensionLabel: 'Tes11Test',
            },
        }));

        expect(wrapper.find('.sw-extension-privacy-policy-extensions-modal__close-button')
            .text()).toBe('global.default.confirm');
    });

    it('should close the modal', async () => {
        wrapper = await createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test',

        });
        expect(wrapper.emitted()).toEqual({});

        await wrapper.find('.sw-extension-privacy-policy-extensions-modal__close-button').trigger('click');

        await wrapper.vm.$nextTick();


        expect(wrapper.emitted()).toEqual({
            'modal-close': [[]],
        });
    });
});
