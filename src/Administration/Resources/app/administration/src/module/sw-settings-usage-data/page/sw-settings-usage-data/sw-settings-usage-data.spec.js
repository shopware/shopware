import { shallowMount } from '@vue/test-utils';
import swSettingsUsageDataPage from 'src/module/sw-settings-usage-data/page/sw-settings-usage-data';
import { ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY } from 'src/core/service/api/usage-data.api.service';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-alert';

Shopware.Component.register('sw-settings-usage-data', swSettingsUsageDataPage);

async function createWrapper(
    isAdmin = true,
    systemConfigValues = {},
) {
    return shallowMount(await Shopware.Component.build('sw-settings-usage-data'), {
        stubs: {
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-alert': await Shopware.Component.build('sw-alert'),

            'sw-page': true,
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
    });
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper = null;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should have a disabled sw-switch-field if the user is not an admin', async () => {
        wrapper = await createWrapper(false);

        const switchField = wrapper.find('[name="sw-field--shareUsageData"]');
        expect(switchField.attributes().disabled).toBe('disabled');
    });

    it('should save the system config when the sw-switch-field is toggled', async () => {
        wrapper = await createWrapper();
        const saveSystemConfigSpy = jest.spyOn(wrapper.vm.systemConfigApiService, 'saveValues');

        const switchField = await wrapper.find('[name="sw-field--shareUsageData"]');
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

        const switchField = await wrapper.find('[name="sw-field--shareUsageData"]');
        expect(switchField.element).not.toBeChecked();
    });

    it('should have an inactive sw-switch-field if the system config is set to false', async () => {
        wrapper = await createWrapper(true, { [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: false });

        const switchField = await wrapper.find('[name="sw-field--shareUsageData"]');
        expect(switchField.element).not.toBeChecked();
    });

    it('should have an active sw-switch-field if the system config is set to true', async () => {
        wrapper = await createWrapper(true, { [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: true });

        const switchField = await wrapper.find('[name="sw-field--shareUsageData"]');
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
