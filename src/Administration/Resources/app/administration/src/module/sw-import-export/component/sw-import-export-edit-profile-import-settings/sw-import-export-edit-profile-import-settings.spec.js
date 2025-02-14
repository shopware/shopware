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
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-inheritance-switch': true,
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

    it.skip('should be a Vue.js component', async () => {
        wrapper = await createWrapper(getProfileMock());
        expect(wrapper.vm).toBeTruthy();
    });

    it.skip('should always keep one switch activated', async () => {
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

    it.skip('should have disabled switch fields when profile is a system default', async () => {
        const profile = getProfileMock();
        profile.systemDefault = true;

        wrapper = await createWrapper(profile);
        await flushPromises();
        const switches = wrapper.findAll('input[type="checkbox"]');

        expect(switches.at(0).attributes('disabled')).toBeDefined();
        expect(switches.at(1).attributes('disabled')).toBeDefined();
    });
});
