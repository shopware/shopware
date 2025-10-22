/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

async function createWrapper(profile) {
    return mount(await wrapTestComponent('sw-import-export-edit-profile-import-settings', { sync: true }), {
        global: {
            stubs: {
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch'),
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        props: {
            profile,
        },
    });
}

function getProfileMock() {
    return {
        systemDefault: false,
        config: { createEntities: true, updateEntities: true },
    };
}

describe('module/sw-import-export/components/sw-import-export-edit-profile-import-settings', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) wrapper.unmount();
    });

    it('should always keep one switch activated', async () => {
        wrapper = await createWrapper(getProfileMock());
        await flushPromises();
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
        await flushPromises();
        const switches = wrapper.findAll('input[type="checkbox"]');

        expect(switches.at(0).attributes('disabled')).toBeDefined();
        expect(switches.at(1).attributes('disabled')).toBeDefined();
    });

    it('should be possible to enable both switches', async () => {
        wrapper = await createWrapper(getProfileMock());
        await flushPromises();

        const createSwitch = await wrapper.find('.sw-import-export-edit-profile-import-settings__create-switch input');
        const updateSwitch = await wrapper.find('.sw-import-export-edit-profile-import-settings__update-switch input');

        await createSwitch.setChecked(false);
        await flushPromises();
        expect(createSwitch.attributes('checked')).toBeUndefined();
        expect(updateSwitch.attributes('checked')).toBeDefined();

        await createSwitch.setChecked(true);
        await flushPromises();
        expect(createSwitch.attributes('checked')).toBeDefined();
        expect(updateSwitch.attributes('checked')).toBeDefined();

        await updateSwitch.setChecked(false);
        await flushPromises();
        expect(createSwitch.attributes('checked')).toBeDefined();
        expect(updateSwitch.attributes('checked')).toBeUndefined();

        await updateSwitch.setChecked(true);
        await flushPromises();
        expect(createSwitch.attributes('checked')).toBeDefined();
        expect(updateSwitch.attributes('checked')).toBeDefined();
    });
});
