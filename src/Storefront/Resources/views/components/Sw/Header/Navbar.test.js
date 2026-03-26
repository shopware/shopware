import { describe, it, expect, vi, beforeEach } from 'vitest';

import 'shopware';

globalThis.activeNavigationId = 'cat-42';

const { default: Navbar } = await import('./Navbar');

function buildEl({ categoryIds = [], flyouts = 0, scrollable = true } = {}) {
    const el = document.createElement('nav');

    const nav = document.createElement('ul');
    nav.className = 'sw-navbar__nav';

    categoryIds.forEach(id => {
        const li = document.createElement('li');
        li.setAttribute('data-category-id', id);
        nav.appendChild(li);
    });

    el.appendChild(nav);

    for (let i = 0; i < flyouts; i++) {
        const flyout = document.createElement('div');
        flyout.className = 'sw-flyout';
        el.appendChild(flyout);
    }

    if (scrollable) {
        const leftBtn = document.createElement('button');
        leftBtn.className = 'sw-navbar__scroller-button is--left';
        const rightBtn = document.createElement('button');
        rightBtn.className = 'sw-navbar__scroller-button is--right';
        el.appendChild(leftBtn);
        el.appendChild(rightBtn);
    }

    return el;
}

describe('Navbar', () => {
    let el;
    let navbar;

    beforeEach(() => {
        vi.clearAllMocks();
        globalThis.activeNavigationId = 'cat-42';
    });

    describe('setActiveState', () => {
        it('adds the active class to the nav item matching activeNavigationId', () => {
            el = buildEl({ categoryIds: ['cat-10', 'cat-42', 'cat-99'] });
            navbar = new Navbar(el, {});
            navbar.init();

            const activeItem = el.querySelector('[data-category-id="cat-42"]');
            expect(activeItem.classList.contains('active')).toBe(true);
        });

        it('does not add active class to non-matching items', () => {
            el = buildEl({ categoryIds: ['cat-10', 'cat-42', 'cat-99'] });
            navbar = new Navbar(el, {});
            navbar.init();

            expect(el.querySelector('[data-category-id="cat-10"]').classList.contains('active')).toBe(false);
            expect(el.querySelector('[data-category-id="cat-99"]').classList.contains('active')).toBe(false);
        });

        it('does nothing gracefully when no item matches', () => {
            globalThis.activeNavigationId = 'nonexistent';
            el = buildEl({ categoryIds: ['cat-10'] });
            navbar = new Navbar(el, {});

            expect(() => navbar.init()).not.toThrow();
        });

        it('uses a custom navItemActiveClassName option', () => {
            el = buildEl({ categoryIds: ['cat-42'] });
            navbar = new Navbar(el, { navItemActiveClassName: 'is-selected' });
            navbar.init();

            expect(el.querySelector('[data-category-id="cat-42"]').classList.contains('is-selected')).toBe(true);
        });
    });

    describe('setFlyoutPosition', () => {
        it('sets the top style on all .sw-flyout elements based on nav height', () => {
            el = buildEl({ flyouts: 2 });
            navbar = new Navbar(el, {});
            navbar.init();

            const flyouts = el.querySelectorAll('.sw-flyout');
            flyouts.forEach(flyout => {
                // In happy-dom, offsetHeight is always 0.
                expect(flyout.style.top).toBe('0px');
            });
        });
    });

    describe('attachMenuScroller', () => {
        it('hides both scroll buttons when the nav fits without scrolling', () => {
            // In happy-dom, scrollLeft = 0, offsetWidth = 0, scrollWidth = 0.
            // atStart = true (0 === 0), atEnd = true (0 + 0 >= 0) → both hidden.
            el = buildEl();
            navbar = new Navbar(el, {});
            navbar.init();

            const leftBtn = el.querySelector('.sw-navbar__scroller-button.is--left');
            const rightBtn = el.querySelector('.sw-navbar__scroller-button.is--right');
            expect(leftBtn.style.display).toBe('none');
            expect(rightBtn.style.display).toBe('none');
        });

        it('does not attach scroll listeners when scrollable is false', () => {
            el = buildEl({ scrollable: false });
            const spy = vi.spyOn(el, 'addEventListener');
            navbar = new Navbar(el, { scrollable: false });
            navbar.init();

            // No scroll event should be registered on the nav container.
            expect(spy).not.toHaveBeenCalledWith('scroll', expect.anything());
        });
    });

    describe('scrollLeft / scrollRight', () => {
        it('scrolls the nav container left by the configured distance', () => {
            el = buildEl();
            navbar = new Navbar(el, {});
            navbar.init();

            const navContainer = el.querySelector('.sw-navbar__nav');
            const scrollBy = vi.spyOn(navContainer, 'scrollBy');

            navbar.scrollLeft();

            expect(scrollBy).toHaveBeenCalledWith({ left: -450, behavior: 'smooth' });
        });

        it('scrolls the nav container right by the configured distance', () => {
            el = buildEl();
            navbar = new Navbar(el, {});
            navbar.init();

            const navContainer = el.querySelector('.sw-navbar__nav');
            const scrollBy = vi.spyOn(navContainer, 'scrollBy');

            navbar.scrollRight();

            expect(scrollBy).toHaveBeenCalledWith({ left: 450, behavior: 'smooth' });
        });

        it('uses a custom scrollDistance option', () => {
            el = buildEl();
            navbar = new Navbar(el, { scrollDistance: 200 });
            navbar.init();

            const navContainer = el.querySelector('.sw-navbar__nav');
            const scrollBy = vi.spyOn(navContainer, 'scrollBy');

            navbar.scrollRight();

            expect(scrollBy).toHaveBeenCalledWith({ left: 200, behavior: 'smooth' });
        });
    });

    describe('destroy', () => {
        it('removes scroll button listeners without throwing', () => {
            el = buildEl();
            navbar = new Navbar(el, {});
            navbar.init();

            expect(() => navbar.destroy()).not.toThrow();
        });
    });
});
