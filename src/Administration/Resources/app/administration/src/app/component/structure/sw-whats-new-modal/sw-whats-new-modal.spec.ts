/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import 'src/app/component/wizard/sw-wizard-dot-navigation';
import swWhatsNewModal from './index';

async function createWrapper(): Promise<VueWrapper> {
    return mount(swWhatsNewModal, {
        global: {
            mocks: {
                $t: (snippet: string) => snippet,
            },
            stubs: {
                teleport: {
                    template: '<div><slot/></div>',
                },
                'sw-wizard-dot-navigation': await wrapTestComponent('sw-wizard-dot-navigation'),
            },
        },
    });
}

describe('src/app/component/structure/sw-whats-new-modal', () => {
    let wrapper: VueWrapper | null = null;

    beforeEach(() => {
        Shopware.Store.get('context').app.firstRunWizard = false;
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
    });

    it('shows the modal with the title in the header', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(true);
        expect(wrapper.get('.mt-modal__title').text()).toBe('sw-whats-new-modal.title');
    });

    it('does not show the modal while the first run wizard is active', async () => {
        Shopware.Store.get('context').app.firstRunWizard = true;

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('renders one navigation dot per page', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.sw-wizard-dot-navigation__item')).toHaveLength(2);
        expect(wrapper.findAll('.sw-wizard-dot-navigation__item').at(0)?.classes()).toContain('is--active');
    });

    it('navigates through the pages and closes on finish', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-whats-new-modal__headline').text()).toBe(
            'sw-whats-new-modal.pages.adminNavigation.headline',
        );
        expect(wrapper.find('.sw-whats-new-modal__footer-left button').exists()).toBe(false);

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');

        expect(wrapper.find('.sw-whats-new-modal__headline').text()).toContain('sw-whats-new-modal.pages.darkMode.headline');
        expect(wrapper.findAll('.sw-wizard-dot-navigation__item').at(1)?.classes()).toContain('is--active');
        expect(wrapper.get('.sw-whats-new-modal__footer-left button').text()).toBe('global.default.back');
        expect(wrapper.get('.sw-whats-new-modal__footer-right button').text()).toBe('sw-whats-new-modal.finishButton');

        await wrapper.get('.sw-whats-new-modal__footer-left button').trigger('click');

        expect(wrapper.find('.sw-whats-new-modal__headline').text()).toBe(
            'sw-whats-new-modal.pages.adminNavigation.headline',
        );

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('closes the modal via the header close button', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.mt-modal__close-button').trigger('click');

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('shows the experimental badge only on the dark mode page', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-whats-new-modal__headline .mt-badge').exists()).toBe(false);

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');

        expect(wrapper.get('.sw-whats-new-modal__headline .mt-badge').text()).toBe(
            'sw-whats-new-modal.pages.darkMode.badge',
        );
    });

    it('renders the video of the active page', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.get('.sw-whats-new-modal__media-video').attributes('src')).toContain('admin-navigation.mp4');

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');

        expect(wrapper.get('.sw-whats-new-modal__media-video').attributes('src')).toContain('dark-mode.mp4');
    });
});
