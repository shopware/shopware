/**
 * @package services-settings
 */
import { shallowMount } from '@vue/test-utils_v2';
import swImportExportEditProfileImportSettings from 'src/module/sw-import-export/component/sw-import-export-edit-profile-import-settings';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

Shopware.Component.register('sw-import-export-edit-profile-import-settings', swImportExportEditProfileImportSettings);

describe('module/sw-import-export/components/sw-import-export-edit-profile-import-settings', () => {
    /** @type Wrapper */
    let wrapper;

    function getProfileMock() {
        return {
            systemDefault: false,
            config: { createEntities: true, updateEntities: true },
        };
    }

    async function createWrapper(profile) {
        return shallowMount(await Shopware.Component.build('sw-import-export-edit-profile-import-settings'), {
            propsData: {
                profile,
            },
            stubs: {
                'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': true,
            },
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(getProfileMock());
        expect(wrapper.vm).toBeTruthy();
    });

    it('should always keep one switch activated', async () => {
        wrapper = await createWrapper(getProfileMock());
        const switches = wrapper.findAll('input[type="checkbox"]');

        expect(wrapper.vm.profile.config.createEntities).toBe(true);
        expect(wrapper.vm.profile.config.updateEntities).toBe(true);

        await switches.at(0).setChecked(false);

        expect(wrapper.vm.profile.config.createEntities).toBe(false);
        expect(wrapper.vm.profile.config.updateEntities).toBe(true);

        await switches.at(1).setChecked(false);

        expect(wrapper.vm.profile.config.createEntities).toBe(true);
        expect(wrapper.vm.profile.config.updateEntities).toBe(false);
    });

    it('should have disabled switch fields when profile is a system default', async () => {
        const profile = getProfileMock();
        profile.systemDefault = true;

        wrapper = await createWrapper(profile);
        const switches = wrapper.findAll('input[type="checkbox"]');

        expect(switches.at(0).attributes('disabled')).toBe('disabled');
        expect(switches.at(1).attributes('disabled')).toBe('disabled');
    });
});
