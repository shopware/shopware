/**
 * @sw-package framework
 */

// @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal

import { type VueWrapper } from '@vue/test-utils';
import createWrapper, { setIntendedAudience } from './create-wrapper';

describe('src/app/component/structure/sw-ui-shell-update-2026-modal - pages', () => {
    let wrapper: VueWrapper;

    beforeEach(async () => {
        setIntendedAudience();

        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('renders one navigation dot per page', () => {
        expect(wrapper.findAll('.sw-wizard-dot-navigation__item')).toHaveLength(2);
        expect(wrapper.findAll('.sw-wizard-dot-navigation__item').at(0)?.classes()).toContain('is--active');
    });

    it('navigates through the pages and closes on finish', async () => {
        expect(wrapper.find('.sw-ui-shell-update-2026-modal__headline').text()).toBe(
            'sw-ui-shell-update-2026-modal.pages.adminNavigation.headline',
        );
        expect(wrapper.find('.sw-ui-shell-update-2026-modal__footer-left button').exists()).toBe(false);

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');

        expect(wrapper.find('.sw-ui-shell-update-2026-modal__headline').text()).toContain(
            'sw-ui-shell-update-2026-modal.pages.darkMode.headline',
        );
        expect(wrapper.findAll('.sw-wizard-dot-navigation__item').at(1)?.classes()).toContain('is--active');
        expect(wrapper.get('.sw-ui-shell-update-2026-modal__footer-left button').text()).toBe('global.default.back');
        expect(wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').text()).toBe(
            'sw-ui-shell-update-2026-modal.finishButton',
        );

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-left button').trigger('click');

        expect(wrapper.find('.sw-ui-shell-update-2026-modal__headline').text()).toBe(
            'sw-ui-shell-update-2026-modal.pages.adminNavigation.headline',
        );

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('closes the modal via the header close button', async () => {
        await wrapper.get('.mt-modal__close-button').trigger('click');

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('shows the experimental badge only on the dark mode page', async () => {
        expect(wrapper.find('.sw-ui-shell-update-2026-modal__headline .mt-badge').exists()).toBe(false);

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');

        expect(wrapper.get('.sw-ui-shell-update-2026-modal__headline .mt-badge').text()).toBe(
            'sw-ui-shell-update-2026-modal.pages.darkMode.badge',
        );
    });

    it('renders both theme variants of the shared imagery', () => {
        // CSS picks the variant by data-theme, so both are in the DOM and swap without a request.
        expect(wrapper.get('.sw-ui-shell-update-2026-modal__media-image--light').attributes('src')).toContain(
            'static/img/ui-shell-update-2026/background-light.jpg',
        );
        expect(wrapper.get('.sw-ui-shell-update-2026-modal__media-image--dark').attributes('src')).toContain(
            'static/img/ui-shell-update-2026/background-dark.jpg',
        );
        expect(wrapper.get('.sw-ui-shell-update-2026-modal__compare-image--after-light').attributes('src')).toContain(
            'static/img/ui-shell-update-2026/navigation-after.jpg',
        );
        expect(wrapper.get('.sw-ui-shell-update-2026-modal__compare-image--after-dark').attributes('src')).toContain(
            'static/img/ui-shell-update-2026/navigation-after-dark.jpg',
        );
    });

    it('keeps the same comparison on both pages', async () => {
        const sources = () =>
            wrapper.findAll('.sw-ui-shell-update-2026-modal__compare-image').map((image) => image.attributes('src'));

        const onNavigationPage = sources();

        expect(onNavigationPage.at(0)).toContain('static/img/ui-shell-update-2026/navigation-before.jpg');

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        expect(sources()).toEqual(onNavigationPage);
    });
});
