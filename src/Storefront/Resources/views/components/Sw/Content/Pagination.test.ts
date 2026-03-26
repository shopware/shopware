import { describe, it, expect, vi, beforeEach } from 'vitest';
// 'shopware' is aliased to __mocks__/shopware.ts in vitest.config.ts so we get
// the stub implementations — no vi.mock() call required.
import { Shopware } from 'shopware';

// Import after the mock alias is established.
const { default: Pagination } = await import('./Pagination');

function buildEl(pages: number[], activePage = 1): HTMLElement {
    const el = document.createElement('nav');
    el.innerHTML = pages
        .map(p => `<li class="page-item page-${p}${p === activePage ? ' active' : ''}"><a class="page-link" data-page="${p}" href="#">${p}</a></li>`)
        .join('');
    return el;
}

describe('Pagination', () => {
    let el: HTMLElement;
    let pagination: InstanceType<typeof Pagination>;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl([1, 2, 3, 4, 5]);
        pagination = new Pagination(el, {});
        pagination.init();
    });

    it('emits Pagination:Change with the clicked page number', () => {
        const anchor = el.querySelector<HTMLAnchorElement>('a[data-page="3"]')!;
        anchor.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(Shopware.emit).toHaveBeenCalledWith('Pagination:Change', 3);
    });

    it('calls emitInterception before emitting the change', () => {
        const anchor = el.querySelector<HTMLAnchorElement>('a[data-page="2"]')!;
        anchor.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(Shopware.emitInterception).toHaveBeenCalledWith('Pagination:PreChange', { page: 2 });
        expect(Shopware.emit).toHaveBeenCalledWith('Pagination:Change', 2);
    });

    it('uses the intercepted page value when emitting', () => {
        vi.mocked(Shopware.emitInterception).mockReturnValueOnce({ page: 99 });

        const anchor = el.querySelector<HTMLAnchorElement>('a[data-page="1"]')!;
        anchor.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(Shopware.emit).toHaveBeenCalledWith('Pagination:Change', 99);
    });

    it('sets the clicked page as active', () => {
        const anchor = el.querySelector<HTMLAnchorElement>('a[data-page="4"]')!;
        anchor.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(el.querySelector('.page-4')?.classList.contains('active')).toBe(true);
    });

    it('removes the active class from all other pages when activating one', () => {
        // Page 1 is active initially; click page 3.
        const anchor = el.querySelector<HTMLAnchorElement>('a[data-page="3"]')!;
        anchor.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(el.querySelector('.page-1')?.classList.contains('active')).toBe(false);
        expect(el.querySelector('.page-2')?.classList.contains('active')).toBe(false);
        expect(el.querySelector('.page-3')?.classList.contains('active')).toBe(true);
    });

    it('prevents default navigation when useHref is false (default)', () => {
        const anchor = el.querySelector<HTMLAnchorElement>('a[data-page="2"]')!;
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        anchor.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it('does not prevent default navigation when useHref is true', () => {
        pagination.destroy();

        const el2 = buildEl([1, 2, 3]);
        const p2 = new Pagination(el2, { useHref: true });
        p2.init();

        const anchor = el2.querySelector<HTMLAnchorElement>('a[data-page="2"]')!;
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        anchor.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it('removes all click listeners on destroy', () => {
        pagination.destroy();

        const anchor = el.querySelector<HTMLAnchorElement>('a[data-page="2"]')!;
        anchor.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(Shopware.emit).not.toHaveBeenCalled();
    });
});
