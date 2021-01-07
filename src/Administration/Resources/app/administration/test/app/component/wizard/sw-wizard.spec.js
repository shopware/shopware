import { shallowMount } from '@vue/test-utils';
import 'src/app/component/wizard/sw-wizard';
import 'src/app/component/wizard/sw-wizard-page';

function createWrapper(options = {}) {
    const pages = [];
    for (let i = 0; i < 5; i += 1) {
        pages.push(Shopware.Component.build('sw-wizard-page'));
    }

    const defaults = {
        stubs: {
            'sw-modal': true,
            'sw-wizard-dot-navigation': true,
            'sw-icon': true,
            'sw-button': true
        },
        mocks: {
            $tc: (t) => t
        },
        slots: {
            default: pages
        },
        provide: {
            shortcutService: {
                startEventListener() {},
                stopEventListener() {}
            }
        },
        propsData: {
            activePage: 3
        }
    };

    return shallowMount(Shopware.Component.build('sw-wizard'), { ...defaults, ...options });
}
describe('src/app/component/wizard/sw-wizard', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a pages count of 5', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.pagesCount).toBe(5);
    });

    it('should fire the necessary events', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        // Check events being fired
        expect(wrapper.emitted()['pages-updated']).toBeTruthy();
        expect(wrapper.emitted()['pages-updated'].length).toBe(5);
        expect(wrapper.emitted()['current-page-change']).toBeTruthy();
        expect(wrapper.emitted()['current-page-change'].length).toBe(1);

        // Check payload
        expect(wrapper.emitted()['current-page-change'][0][0]).toBe(3);
    });

    it('should be able to add a new page', async () => {
        const wrapper = createWrapper();

        wrapper.vm.addPage(Shopware.Component.build('sw-wizard-page'));

        expect(wrapper.vm.pagesCount).toBe(6);

        await wrapper.vm.$nextTick();
        const emitted = wrapper.emitted();

        expect(emitted['pages-updated'].length).toBe(6);
        expect(emitted['pages-updated'][emitted['pages-updated'].length - 1][2]).toBe('add');
    });

    it('should be able to remove an existing page', async () => {
        const wrapper = createWrapper();
        const pageToRemove = wrapper.vm.pages[wrapper.vm.pages.length - 1];

        wrapper.vm.removePage(pageToRemove);

        expect(wrapper.vm.pagesCount).toBe(4);

        await wrapper.vm.$nextTick();
        const emitted = wrapper.emitted();

        expect(emitted['pages-updated'].length).toBe(6);
        expect(emitted['pages-updated'][emitted['pages-updated'].length - 1][2]).toBe('remove');
    });

    it('should be possible to navigate the wizard', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.currentlyActivePage).toBe(3);
        wrapper.vm.nextPage();
        expect(wrapper.vm.currentlyActivePage).toBe(4);
        wrapper.vm.previousPage();
        expect(wrapper.vm.currentlyActivePage).toBe(3);
    });
});
