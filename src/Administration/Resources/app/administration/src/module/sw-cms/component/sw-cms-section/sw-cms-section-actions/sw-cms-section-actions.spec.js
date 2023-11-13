/**
 * @package buyers-experience
 */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import swCmsSectionActions from 'src/module/sw-cms/component/sw-cms-section/sw-cms-section-actions';

Shopware.Component.register('sw-cms-section-actions', swCmsSectionActions);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-cms-section-actions'), {
        localVue,
        propsData: {
            section: {},
        },
        stubs: {
            'sw-icon': true,
        },
    });
}

describe('module/sw-cms/component/sw-cms-section-actions', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                selectedSection: {},
            },
            actions: {
                setSection: () => {},
            },
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain disabled styling', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.classes()).toContain('is--disabled');
    });

    it('should not contain disabled styling', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');
    });
});
