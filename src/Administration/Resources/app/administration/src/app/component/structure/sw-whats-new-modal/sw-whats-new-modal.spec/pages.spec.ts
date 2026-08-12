/**
 * @sw-package framework
 */

import { type VueWrapper } from '@vue/test-utils';
import createWrapper, { setIntendedAudience } from './create-wrapper';

describe('src/app/component/structure/sw-whats-new-modal - pages', () => {
    let wrapper: VueWrapper | null = null;

    beforeEach(() => {
        setIntendedAudience();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
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

    it('renders both theme variants of the shared imagery', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        // Which variant shows is left to the data-theme attribute in CSS, so both are in
        // the DOM from the start and the browser fetches them without a preload of our own.
        expect(wrapper.get('.sw-whats-new-modal__media-image--light').attributes('src')).toContain(
            'static/img/whats-new/background-light.jpg',
        );
        expect(wrapper.get('.sw-whats-new-modal__media-image--dark').attributes('src')).toContain(
            'static/img/whats-new/background-dark.jpg',
        );
        expect(wrapper.get('.sw-whats-new-modal__compare-image--after-light').attributes('src')).toContain(
            'static/img/whats-new/sidebar-after.jpg',
        );
        expect(wrapper.get('.sw-whats-new-modal__compare-image--after-dark').attributes('src')).toContain(
            'static/img/whats-new/sidebar-after-dark.jpg',
        );
    });

    it('keeps the same comparison on both pages', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const sources = () => wrapper?.findAll('.sw-whats-new-modal__compare-image').map((image) => image.attributes('src'));

        const onNavigationPage = sources();

        expect(onNavigationPage?.at(0)).toContain('static/img/whats-new/sidebar-before.jpg');

        await wrapper.get('.sw-whats-new-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(sources()).toEqual(onNavigationPage);
    });
});
