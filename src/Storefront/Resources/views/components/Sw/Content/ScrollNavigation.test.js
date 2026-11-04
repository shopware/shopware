import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
// Import order is load-bearing: this module's side effect installs the `globalThis.ShopwareComponent`
// that the component below extends as a bare global while its own module is evaluated.
import { Shopware } from 'shopware'; // eslint-disable-line no-unused-vars
import ScrollNavigation from './ScrollNavigation';

function createScrollNavigation(targetIds = ['sw-scroll-nav-a', 'sw-scroll-nav-b']) {
    document.body.innerHTML = '';

    const nav = document.createElement('nav');
    nav.innerHTML = targetIds
        .map((id) => `<a class="sw-scroll-navigation__link" href="#${id}" data-scroll-navigation-target="${id}"></a>`)
        .join('');
    document.body.appendChild(nav);

    const targets = targetIds.map((id) => {
        const target = document.createElement('div');
        target.id = id;
        document.body.appendChild(target);

        return target;
    });

    // The `ShopwareComponent` test double does not call `init()` from its constructor.
    const component = new ScrollNavigation(nav);

    return { component, nav, targets };
}

function placeTargets(targets, tops) {
    targets.forEach((target, index) => {
        vi.spyOn(target, 'getBoundingClientRect').mockReturnValue({ top: tops[index] });
    });
}

describe('Sw:Content:ScrollNavigation', () => {
    let cssSupports;

    beforeEach(() => {
        window.innerHeight = 1000;
        cssSupports = vi.fn(() => false);
        vi.stubGlobal('CSS', { supports: cssSupports });
    });

    afterEach(() => {
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('marks the last target above the activation line as the current link', () => {
        const { component, nav, targets } = createScrollNavigation();
        placeTargets(targets, [-200, 600]);

        component.init();

        const links = nav.querySelectorAll('a');
        expect(links[0].classList.contains('is--active')).toBe(true);
        expect(links[0].getAttribute('aria-current')).toBe('true');
        expect(links[1].classList.contains('is--active')).toBe(false);
        expect(links[1].hasAttribute('aria-current')).toBe(false);
    });

    it('falls back to the first target while nothing has been scrolled past', () => {
        const { component, nav, targets } = createScrollNavigation();
        placeTargets(targets, [800, 1600]);

        component.init();

        expect(nav.querySelector('a').classList.contains('is--active')).toBe(true);
    });

    it('moves the active marker on scroll', () => {
        const { component, nav, targets } = createScrollNavigation();
        placeTargets(targets, [100, 900]);
        component.init();

        placeTargets(targets, [-900, 100]);
        window.dispatchEvent(new Event('scroll'));

        const links = nav.querySelectorAll('a');
        expect(links[0].classList.contains('is--active')).toBe(false);
        expect(links[1].classList.contains('is--active')).toBe(true);
    });

    it('leaves the active state to :target-current when the browser supports it', () => {
        cssSupports.mockReturnValue(true);
        const addEventListener = vi.spyOn(window, 'addEventListener');
        const { component, nav, targets } = createScrollNavigation();
        placeTargets(targets, [-200, 100]);

        component.init();

        expect(addEventListener).not.toHaveBeenCalledWith('scroll', expect.any(Function), expect.anything());
        expect(nav.querySelector('.is--active')).toBeNull();
    });

    it('does nothing without resolvable targets', () => {
        const addEventListener = vi.spyOn(window, 'addEventListener');
        const { component } = createScrollNavigation([]);

        component.init();
        component.destroy();

        expect(addEventListener).not.toHaveBeenCalledWith('scroll', expect.any(Function), expect.anything());
    });
});
