/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-users-permissions-configuration', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-system-config': true,
                },
            },
        },
    );
}

describe('module/sw-users-permissions/components/sw-users-permissions-configuration', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should emit the event correctly', () => {
        wrapper.vm.onChangeLoading(true);
        expect(wrapper.emitted('loading-change')).toBeTruthy();
    });
});
