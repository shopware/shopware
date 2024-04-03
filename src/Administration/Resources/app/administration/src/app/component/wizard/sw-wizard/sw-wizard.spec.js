/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/wizard/sw-wizard';
import 'src/app/component/wizard/sw-wizard-page';

async function createWrapper(options = {}) {
    const pages = [];

    for (let i = 0; i < 5; i += 1) {
        // eslint-disable-next-line no-await-in-loop
        const page = await wrapTestComponent('sw-wizard-page');
        page.props = {
            position: i,
        };
        pages.push(page);
    }

    const defaults = {
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-wizard-dot-navigation': await wrapTestComponent('sw-wizard-dot-navigation'),
                'sw-icon': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
            },
            provide: {
                shortcutService: {
                    startEventListener() {},
                    stopEventListener() {},
                },
            },
        },
        slots: {
            default: pages,
        },
        props: {
            activePage: 3,
        },
    };

    return mount(await wrapTestComponent('sw-wizard', { sync: true }), { ...defaults, ...options });
}
describe('src/app/component/wizard/sw-wizard', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a pages count of 5', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.pagesCount).toBe(5);
    });

    it('should fire the necessary events', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // Check events being fired
        expect(wrapper.emitted()['pages-updated']).toBeTruthy();
        expect(wrapper.emitted()['pages-updated']).toHaveLength(5);
        expect(wrapper.emitted()['current-page-change']).toBeTruthy();

        // Check payload
        expect(wrapper.emitted()['current-page-change'][0][0]).toBe(3);
    });

    it('should be able to add a new page', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

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
        await flushPromises();

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
        await flushPromises();

        expect(wrapper.vm.currentlyActivePage).toBe(3);
        wrapper.vm.nextPage();
        expect(wrapper.vm.currentlyActivePage).toBe(4);
        wrapper.vm.previousPage();
        expect(wrapper.vm.currentlyActivePage).toBe(3);
    });
});
