import { mount } from '@vue/test-utils_v3';
import { ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY } from 'src/core/service/api/usage-data.api.service';

async function createWrapper(
    isAdmin = true,
    systemConfigValues = {},
) {
    const wrapper = mount(await wrapTestComponent('sw-settings-usage-data', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-alert': await wrapTestComponent('sw-alert'),

                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot></slot>
                        </div>
                    `,
                },
                'sw-search-bar': true,
                'sw-help-center': true,
                'sw-notification-center': true,
                'sw-app-actions': true,
                'sw-card-view': true,
                'sw-card': true,
                'sw-settings-usage-data-intro': true,
                'sw-field-error': true,
            },

            provide: {
                acl: {
                    isAdmin: () => isAdmin,
                },
                systemConfigApiService: {
                    getValues: () => Promise.resolve(systemConfigValues),
                    saveValues: () => Promise.resolve(),
                },
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper = null;

    it('should have a disabled sw-switch-field if the user is not an admin', async () => {
        wrapper = await createWrapper(false);
        await flushPromises();

        const switchField = wrapper.find('.sw-field[label="sw-settings-usage-data.general.shareUsageData"]');
        expect(switchField.classes()).toContain('is--disabled');
    });

    it('should save the system config when the sw-switch-field is toggled', async () => {
        wrapper = await createWrapper();
        const saveSystemConfigSpy = jest.spyOn(wrapper.vm.systemConfigApiService, 'saveValues');

        const switchField = await wrapper.find('.sw-field--switch input');
        expect(switchField.element).not.toBeChecked();

        await switchField.setChecked(true);
        expect(switchField.element).toBeChecked();

        await switchField.setChecked(false);
        expect(switchField.element).not.toBeChecked();

        expect(saveSystemConfigSpy).toHaveBeenCalledWith({ [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: true });
        expect(saveSystemConfigSpy).toHaveBeenLastCalledWith({ [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: false });
    });

    it('should have an inactive sw-switch-field if the system config does not yet exist', async () => {
        wrapper = await createWrapper();

        const switchField = await wrapper.find('.sw-field--switch input');
        expect(switchField.element).not.toBeChecked();
    });

    it('should have an inactive sw-switch-field if the system config is set to false', async () => {
        wrapper = await createWrapper(true, { [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: false });

        const switchField = await wrapper.find('.sw-field--switch input');
        expect(switchField.element).not.toBeChecked();
    });

    it('should have an active sw-switch-field if the system config is set to true', async () => {
        wrapper = await createWrapper(true, { [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: true });

        const switchField = await wrapper.find('.sw-field--switch input');
        expect(switchField.element).toBeChecked();
    });

    it('should have a hint in the alert if the user is not an admin', async () => {
        wrapper = await createWrapper(false);

        const alert = await wrapper.find('.sw-alert');
        expect(alert.text()).toBe('sw-settings-usage-data.general.alertText sw-settings-usage-data.general.alertTextOnlyAdmins');
    });

    it('should not have a hint in the alert if the user is an admin', async () => {
        wrapper = await createWrapper();

        const alert = await wrapper.find('.sw-alert');
        expect(alert.text()).toBe('sw-settings-usage-data.general.alertText');
    });
});
