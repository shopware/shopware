/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-profile-index-general', { sync: true }), {
        global: {
            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-text-field': true,
                'sw-select-field': true,
                'sw-password-field': {
                    template: '<input class="sw-password-field" :value="value" @input="$emit(\'update:value\', $event.target.value)">',
                    props: {
                        value: '',
                    },
                },
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-upload-listener': {
                    emits: ['click', 'media-upload-finish'],
                    template: `<div
                        class="sw-upload-listener"
                        @click="$emit('click', $event)"
                        @media-upload-finish="$emit('media-upload-finish', $event)"
                    ></div>`,
                },
                'sw-media-upload-v2': {
                    emits: ['media-drop', 'media-upload-remove-image', 'media-upload-sidebar-open'],
                    template: `<div
                        class="sw-media-upload-v2"
                        @media-drop="$emit('media-drop', $event)"
                        @media-upload-remove-image="$emit('media-upload-remove-image', $event)"
                        @media-upload-sidebar-open="$emit('media-upload-sidebar-open', $event)"
                    >
                        <slot name="upload"></slot>
                        <slot name="preview"></slot>
                    </div>`,
                },
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
                'sw-icon': true,
                'sw-inheritance-switch': true,
                'sw-help-text': true,
                'sw-field-error': true,
            },
            provide: {
                acl: {
                    can: (key) => {
                        if (!key) {
                            return true;
                        }

                        return privileges.includes(key);
                    },
                },
            },
        },
        props: {
            user: {},
            languages: [],
            newPassword: null,
            newPasswordConfirm: null,
            avatarMediaItem: null,
            isUserLoading: false,
            languageId: null,
            isDisabled: true,
            userRepository: {
                schema: {
                    entity: '',
                },
            },
            timezoneOptions: [
                {
                    label: 'UTC',
                    value: 'UTC',
                },
            ],
        },
    });
}

describe('src/module/sw-profile/view/sw-profile-index-general', () => {
    it('should be able to change new password', async () => {
        const wrapper = await createWrapper(['user.update_profile']);
        await flushPromises();

        const changeNewPasswordField = wrapper.find('.sw-password-field:nth-of-type(1)');
        await changeNewPasswordField.setValue('Shopware');
        await changeNewPasswordField.trigger('input');
        await flushPromises();

        expect(wrapper.emitted('new-password-change')[0][0]).toBe('Shopware');
    });

    it('should be able to change new password confirm', async () => {
        const wrapper = await createWrapper(['user.update_profile']);
        await flushPromises();

        const changeNewPasswordConfirmField = wrapper.find('.sw-password-field:nth-of-type(2)');
        await changeNewPasswordConfirmField.setValue('Shopware');
        await changeNewPasswordConfirmField.trigger('input');
        await flushPromises();

        expect(wrapper.emitted('new-password-confirm-change')[0][0]).toBe('Shopware');
    });

    it('should be able to upload media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.sw-upload-listener')
            .trigger('media-upload-finish', { targetId: 'targetId' });

        expect(wrapper.emitted('media-upload')[0][0].targetId).toBe('targetId');
    });

    it('should be able to drop media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.sw-media-upload-v2').trigger('media-drop', { id: 'targetId' });

        expect(wrapper.emitted('media-upload')[0][0].targetId).toBe('targetId');
    });

    it('should be able to remove media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.sw-media-upload-v2').trigger('media-upload-remove-image');

        expect(wrapper.emitted('media-remove')[0]).toHaveLength(0);
    });

    it('should be able to open media', async () => {
        const wrapper = await createWrapper(['media.creator']);
        await flushPromises();

        await wrapper.find('.sw-media-upload-v2').trigger('media-upload-sidebar-open');

        expect(wrapper.emitted('media-open')[0]).toHaveLength(0);
    });

    it('should be able to select timezone', async () => {
        const wrapper = await createWrapper(['user.update_profile']);
        await flushPromises();

        await wrapper.find('.sw-profile--timezone .sw-single-select__selection-input').trigger('click');
        await flushPromises();

        const results = wrapper.findAll('.sw-select-result');
        const resultNames = results.map(result => result.text());

        expect(resultNames).toContain('UTC');
    });
});
