import { describe, it, expect, vi, beforeEach } from 'vitest';

import 'shopware';
const { default: FilterItem } = await import('./Item');

function buildEl() {
    const el = document.createElement('div');
    el.innerHTML = `
        <div class="sw-filter"></div>
        <span class="sw-filter-item__count is--hidden"></span>
    `;
    return el;
}

describe('FilterItem', () => {
    let el;
    let item;

    beforeEach(() => {
        vi.clearAllMocks();
        el = buildEl();
        item = new FilterItem(el, {});
        item.init();
    });

    describe('updateBadge', () => {
        it('sets text content and shows badge when content is provided', () => {
            item.updateBadge('3');

            const badge = el.querySelector('.sw-filter-item__count');
            expect(badge.textContent).toBe('3');
            expect(badge.classList.contains('is--hidden')).toBe(false);
        });

        it('clears text content and hides badge when content is empty string', () => {
            item.updateBadge('3');
            item.updateBadge('');

            const badge = el.querySelector('.sw-filter-item__count');
            expect(badge.textContent).toBe('');
            expect(badge.classList.contains('is--hidden')).toBe(true);
        });

        it('hides badge when content is falsy', () => {
            item.updateBadge(null);

            expect(el.querySelector('.sw-filter-item__count').classList.contains('is--hidden')).toBe(true);
        });
    });

    describe('reset', () => {
        it('clears the badge and hides it', () => {
            item.updateBadge('5');
            item.reset();

            const badge = el.querySelector('.sw-filter-item__count');
            expect(badge.textContent).toBe('');
            expect(badge.classList.contains('is--hidden')).toBe(true);
        });
    });

    describe('handleFilterUpdateBadge', () => {
        it('updates the badge via a Filter:UpdateBadge custom event on the filter element', () => {
            const filterEl = el.querySelector('.sw-filter');
            filterEl.dispatchEvent(new CustomEvent('Filter:UpdateBadge', { detail: { content: '7' } }));

            expect(el.querySelector('.sw-filter-item__count').textContent).toBe('7');
        });

        it('hides the badge when event detail content is empty', () => {
            item.updateBadge('2');
            const filterEl = el.querySelector('.sw-filter');
            filterEl.dispatchEvent(new CustomEvent('Filter:UpdateBadge', { detail: { content: '' } }));

            expect(el.querySelector('.sw-filter-item__count').classList.contains('is--hidden')).toBe(true);
        });
    });
});
