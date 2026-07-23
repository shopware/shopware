/**
 * @sw-package buyers-experience
 */
import { DOMWrapper, mount } from '@vue/test-utils';

let SwShortcutOverview;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-help-center-v2', { sync: true }), {
        attachTo: document.body,
        global: {
            provide: {
                shortcutService: {
                    isShortcutsDisabled: jest.fn(() => false),
                    setShortcutsDisabled: jest.fn(),
                },
            },
            stubs: {
                'sw-shortcut-overview': SwShortcutOverview,
            },
        },
    });
}

function menuItems() {
    return new DOMWrapper(document.body).findAll('.mt-action-menu-item');
}

describe('src/app/component/utils/sw-help-center', () => {
    let wrapper;

    beforeAll(async () => {
        SwShortcutOverview = await wrapTestComponent('sw-shortcut-overview');
    });

    beforeEach(() => {
        const store = Shopware.Store.get('adminHelpCenter');
        store.showHelpSidebar = false;
        store.showShortcutModal = false;
    });

    afterEach(() => {
        wrapper?.unmount();
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    it('should open the help center menu when the trigger is clicked', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(menuItems()).toHaveLength(0);

        await wrapper.find('.sw-help-center__button').trigger('click');
        await flushPromises();

        expect(Shopware.Store.get('adminHelpCenter').showHelpSidebar).toBe(true);
        expect(menuItems().length).toBeGreaterThan(0);
    });

    it('should open the shortcut overview when the shortcut item is selected', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-help-center__button').trigger('click');
        await flushPromises();

        const items = menuItems();
        await items.at(items.length - 1).trigger('click');
        await flushPromises();

        expect(Shopware.Store.get('adminHelpCenter').showShortcutModal).toBe(true);
    });
});
