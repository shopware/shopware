import { mount } from '@vue/test-utils';

async function createWrapper(props) {
    return mount(await wrapTestComponent('sw-extension-privacy-policy-extensions-modal', { sync: true }), {
        global: {
            mocks: {
                $tc: (path, choice, values) => {
                    if (values) {
                        return JSON.stringify({ path, choice, values });
                    }

                    return path;
                },
            },
            stubs: {
                'sw-button': await wrapTestComponent('sw-button', {
                    sync: true,
                }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-modal': {
                    // eslint-disable-next-line max-len
                    template:
                        '<div class="sw-modal"><p class="title">{{ title }}</p><slot></slot><slot name="modal-footer"></slot></div>',
                    props: ['title'],
                },
                'router-link': true,
                'sw-loader': true,
            },
        },
        props: {
            ...props,
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-extension-privacy-policy-extensions-modal', () => {
    it('should display the values', async () => {
        const wrapper = await createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test',
        });
        expect(wrapper.find('.sw-extension-privacy-policy-extensions-modal__text').text()).toBe('a privacy notice');
        expect(wrapper.find('.title').text()).toBe(
            JSON.stringify({
                path: 'sw-extension-store.component.sw-extension-privacy-policy-extensions-modal.title',
                choice: 0,
                values: {
                    extensionLabel: 'Tes11Test',
                },
            }),
        );

        expect(wrapper.find('.sw-extension-privacy-policy-extensions-modal__close-button').text()).toBe(
            'global.default.confirm',
        );
    });

    it('should close the modal', async () => {
        const wrapper = await createWrapper({
            privacyPolicyExtension: 'a privacy notice',
            extensionName: 'Tes11Test',
        });
        expect(wrapper.emitted()).not.toHaveProperty('modal-close');

        await wrapper.find('.sw-extension-privacy-policy-extensions-modal__close-button').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()).toHaveProperty('modal-close');
    });
});
