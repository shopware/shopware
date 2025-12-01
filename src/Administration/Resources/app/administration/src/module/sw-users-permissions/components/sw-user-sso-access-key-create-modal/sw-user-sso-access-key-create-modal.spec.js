/**
 * @internal
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

function createDefaultProps() {
    return {
        isLoading: false,
        isOpen: true,
        accessKey: 'abdcefghi',
        secretAccessKey: '123456789',
        mode: 'view',
    };
}

async function createWrapper(props) {
    const wrapper = mount(
        await wrapTestComponent('sw-user-sso-access-key-create-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),
                    'sw-loader': true,
                },
                provide: {
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
            },
            props,
        },
    );

    await flushPromises();

    return wrapper;
}

describe('module/sw-users-permissions/components/sw-user-sso-access-key-create-modal', () => {
    it('should show the "view" mode', async () => {
        const defaultProps = createDefaultProps();
        const wrapper = await createWrapper(defaultProps);

        const accessKeyField = wrapper.find('.sso-user-Integrations-modal-field--access-key');
        const secretAccessKeyField = wrapper.find('.sso-user-Integrations-modal-field--secret-acces-key');
        const bannerMessage = wrapper.find('.mt-banner__message');
        const generateAccessKeyButton = wrapper.find('.sso-user-Integrations-modal-field--generate-access-key');
        const cancelButton = wrapper.find('.sso-user-Integrations-modal--cancel');
        const saveButton = wrapper.find('.sso-user-Integrations-modal--apply');

        expect(accessKeyField.find('input').attributes('value')).toBe('abdcefghi');

        expect(secretAccessKeyField.attributes('copyable')).toBeUndefined();

        const secretAccessKeyFieldInput = secretAccessKeyField.find('input');
        expect(secretAccessKeyFieldInput.attributes('type')).toBe('password');
        expect(secretAccessKeyFieldInput.attributes('disabled')).toBeDefined();
        expect(secretAccessKeyFieldInput.attributes('disabled')).toBe('');

        expect(bannerMessage.text()).toBe('sw-users-permissions.users.user-detail.modal.hintCreateNewApiKeys');

        expect(generateAccessKeyButton.attributes('class')).toContain('mt-button--critical');

        expect(cancelButton).toBeDefined();
        expect(saveButton).toBeDefined();

        await generateAccessKeyButton.trigger('click');
        expect(wrapper.emitted('access-key-modal-create:generate')).toBeTruthy();
        await cancelButton.trigger('click');
        expect(wrapper.emitted('access-key-modal-create:cancel')).toBeTruthy();
        await saveButton.trigger('click');
        expect(wrapper.emitted('access-key-modal-create:save')).toBeTruthy();
    });

    it('should show the "create" mode', async () => {
        const defaultProps = createDefaultProps();
        defaultProps.mode = 'create';

        const wrapper = await createWrapper(defaultProps);

        const accessKeyField = wrapper.find('.sso-user-Integrations-modal-field--access-key');
        const secretAccessKeyField = wrapper.find('.sso-user-Integrations-modal-field--secret-acces-key');
        const bannerMessage = wrapper.find('.mt-banner__message');
        const generateAccessKeyButton = wrapper.find('.sso-user-Integrations-modal-field--generate-access-key');
        const cancelButton = wrapper.find('.sso-user-Integrations-modal--cancel');
        const saveButton = wrapper.find('.sso-user-Integrations-modal--apply');

        expect(accessKeyField.find('input').attributes('value')).toBe('abdcefghi');
        expect(secretAccessKeyField.find('input').attributes('value')).toBe('123456789');
        expect(bannerMessage.text()).toBe('sw-users-permissions.users.user-detail.modal.secretHelpText');
        expect(generateAccessKeyButton.exists()).toBeFalsy();

        await cancelButton.trigger('click');
        expect(wrapper.emitted('access-key-modal-create:cancel')).toBeTruthy();
        await saveButton.trigger('click');
        expect(wrapper.emitted('access-key-modal-create:save')).toBeTruthy();
    });

    it('should show the "edit" mode', async () => {
        const defaultProps = createDefaultProps();
        defaultProps.mode = 'edit';

        const wrapper = await createWrapper(defaultProps);

        const accessKeyField = wrapper.find('.sso-user-Integrations-modal-field--access-key');
        const secretAccessKeyField = wrapper.find('.sso-user-Integrations-modal-field--secret-acces-key');
        const bannerMessage = wrapper.find('.mt-banner__message');
        const generateAccessKeyButton = wrapper.find('.sso-user-Integrations-modal-field--generate-access-key');
        const cancelButton = wrapper.find('.sso-user-Integrations-modal--cancel');
        const saveButton = wrapper.find('.sso-user-Integrations-modal--apply');

        expect(accessKeyField.find('input').attributes('value')).toBe('abdcefghi');
        expect(secretAccessKeyField.find('input').attributes('value')).toBe('123456789');
        expect(bannerMessage.text()).toBe('sw-users-permissions.users.user-detail.modal.secretHelpText');
        expect(generateAccessKeyButton.exists()).toBeFalsy();

        await cancelButton.trigger('click');
        expect(wrapper.emitted('access-key-modal-create:cancel')).toBeTruthy();
        await saveButton.trigger('click');
        expect(wrapper.emitted('access-key-modal-create:save')).toBeTruthy();
    });
});
