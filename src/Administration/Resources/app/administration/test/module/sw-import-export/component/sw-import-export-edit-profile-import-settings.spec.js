import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-import-export/component/sw-import-export-edit-profile-import-settings';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

describe('module/sw-import-export/components/sw-import-export-edit-profile-import-settings', () => {
    /** @type Wrapper */
    let wrapper;

    function getProfileMock() {
        return {
            systemDefault: false,
            config: { createEntities: true, updateEntities: true }
        };
    }

    function createWrapper(profile) {
        return shallowMount(Shopware.Component.build('sw-import-export-edit-profile-import-settings'), {
            propsData: {
                profile
            },
            stubs: {
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true
            }
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        wrapper = createWrapper(getProfileMock());
        expect(wrapper.vm).toBeTruthy();
    });

    it('should always keep one switch activated', async () => {
        wrapper = createWrapper(getProfileMock());
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

    it('should have disabled switch fields when profile is a system default', () => {
        const profile = getProfileMock();
        profile.systemDefault = true;

        wrapper = createWrapper(profile);
        const switches = wrapper.findAll('input[type="checkbox"]');

        expect(switches.at(0).attributes('disabled')).toBe('disabled');
        expect(switches.at(1).attributes('disabled')).toBe('disabled');
    });
});
