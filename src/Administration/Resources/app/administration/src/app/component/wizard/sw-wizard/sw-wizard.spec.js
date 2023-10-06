/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/wizard/sw-wizard';
import 'src/app/component/wizard/sw-wizard-page';

async function createWrapper(options = {}) {
    const pages = [];

    for (let i = 0; i < 5; i += 1) {
        // eslint-disable-next-line no-await-in-loop
        const page = await Shopware.Component.build('sw-wizard-page');
        page.props = {
            position: i,
        };
        pages.push(page);
    }

    const defaults = {
        stubs: {
            'sw-modal': true,
            'sw-wizard-dot-navigation': true,
            'sw-icon': true,
            'sw-button': true,
            'sw-wizard-page': true,
        },
        slots: {
            default: pages,
        },
        provide: {
            shortcutService: {
                startEventListener() {},
                stopEventListener() {},
            },
        },
        propsData: {
            activePage: 3,
        },
    };

    return shallowMount(await Shopware.Component.build('sw-wizard'), { ...defaults, ...options });
}
describe('src/app/component/wizard/sw-wizard', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a pages count of 5', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.pagesCount).toBe(5);
    });

    it('should fire the necessary events', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        // Check events being fired
        expect(wrapper.emitted()['pages-updated']).toBeTruthy();
        expect(wrapper.emitted()['pages-updated']).toHaveLength(5);
        expect(wrapper.emitted()['current-page-change']).toBeTruthy();
        expect(wrapper.emitted()['current-page-change']).toHaveLength(1);

        // Check payload
        expect(wrapper.emitted()['current-page-change'][0][0]).toBe(3);
    });

    it('should be able to add a new page', async () => {
        const wrapper = await createWrapper();

        const page = await Shopware.Component.build('sw-wizard-page');
        page.props = {
            position: 5,
        };
        wrapper.vm.addPage(page);

        expect(wrapper.vm.pagesCount).toBe(6);

        await wrapper.vm.$nextTick();
        const emitted = wrapper.emitted();

        expect(emitted['pages-updated']).toHaveLength(6);
        expect(emitted['pages-updated'][emitted['pages-updated'].length - 1][2]).toBe('add');
    });

    it('should be able to remove an existing page', async () => {
        const wrapper = await createWrapper();
        const pageToRemove = wrapper.vm.pages[wrapper.vm.pages.length - 1];

        wrapper.vm.removePage(pageToRemove);

        expect(wrapper.vm.pagesCount).toBe(4);

        await wrapper.vm.$nextTick();
        const emitted = wrapper.emitted();

        expect(emitted['pages-updated']).toHaveLength(6);
        expect(emitted['pages-updated'][emitted['pages-updated'].length - 1][2]).toBe('remove');
    });

    it('should be possible to navigate the wizard', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.currentlyActivePage).toBe(3);
        wrapper.vm.nextPage();
        expect(wrapper.vm.currentlyActivePage).toBe(4);
        wrapper.vm.previousPage();
        expect(wrapper.vm.currentlyActivePage).toBe(3);
    });
});
