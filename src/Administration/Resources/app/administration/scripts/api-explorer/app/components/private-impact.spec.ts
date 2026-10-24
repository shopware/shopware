/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { change, productDetailFixture } from '../__fixtures__/compare-fixture';
import { ComponentsView } from '../views/components-view';
import { OverviewView } from '../views/overview-view';
import { ShopwareObjectView } from '../views/shopware-object-view';

function privateFixture() {
    const result = productDetailFixture();
    result.diff.changes.push(
        change('private', 'emit', 'sw-button-process', 'animation-finished', { impact: 'added' }),
        change('private', 'emit', 'sw-button-process', 'process-finish', { impact: 'breaking', change: 'removed' }),
    );

    return result;
}

function mountOverview() {
    return mount(OverviewView, { props: { result: privateFixture() } });
}

function rows(wrapper: ReturnType<typeof mountOverview>): string[] {
    return wrapper.findAll('[data-testid="change-row"]').map((row) => row.text());
}

describe('api-explorer/app private change impact', () => {
    it('counts private changes by impact in the "Private API" row and filters the list by a card', async () => {
        const wrapper = mountOverview();
        const cards = wrapper.findAll('[data-testid="private-cards"] .tile');

        expect(cards.map((card) => card.text())).toEqual([
            '×Breaking 2',
            '!Potentially breaking 0',
            '~Compatible change 1',
            '+Added 1',
            '↓Newly deprecated 0',
            '−Removed (was deprecated) 0',
        ]);

        await cards[0].trigger('click');

        expect(cards[0].attributes('aria-pressed')).toBe('true');
        expect(rows(wrapper)).toEqual([
            expect.stringContaining('Shopware.Internal › secret'),
            expect.stringContaining('process-finish'),
        ]);
        expect(wrapper.text()).toContain('2 × Private · Breaking.');

        await cards[0].trigger('click');

        expect(rows(wrapper)).toHaveLength(9);
    });

    it('keeps the first-row "Private" tile as the filter for all private changes', async () => {
        const wrapper = mountOverview();
        const privateTile = wrapper.findAll('.tiles').at(0)!.findAll('.tile').at(-1)!;

        expect(privateTile.text()).toContain('all private changes');

        await privateTile.trigger('click');

        expect(rows(wrapper)).toHaveLength(4);
    });

    it('renders private changes as combined pills with the impact symbol and colour', () => {
        const wrapper = mountOverview();
        const pill = wrapper
            .findAll('[data-testid="change-row"]')
            .find((row) => row.text().includes('secret'))!
            .find('.pill');

        expect(pill.text()).toBe('×Private · Breaking');
        expect(pill.attributes('data-severity')).toBe('breaking');
    });

    it('names @internal entries in the pill and counts component chips by impact', () => {
        const wrapper = mount(ComponentsView, { props: { result: privateFixture() } });
        const row = wrapper
            .findAll('[data-testid="component-row"]')
            .find((entry) => entry.text().includes('sw-button-process'))!;

        expect(row.findAll('.pill').map((pill) => pill.text())).toEqual([
            '×1 Private · Breaking',
            '~1 @internal · Compatible change',
            '+1 Private · Added',
        ]);
    });

    it('shows the combined pill in the object tree and the details panel', () => {
        const wrapper = mount(ShopwareObjectView, { props: { result: privateFixture(), path: 'Shopware.Internal.secret' } });

        expect(wrapper.find('[data-path="Shopware.Internal.secret"] .pill').text()).toBe('×Private · Breaking');
        expect(wrapper.find('[data-testid="object-detail"] .pill[data-severity="breaking"]').text()).toBe(
            '×Private · Breaking',
        );
    });

    it('orders private rows by impact, with a missing impact last', async () => {
        const result = productDetailFixture();
        result.diff.changes.push(
            change('private', 'emit', 'sw-a', 'added-first', { impact: 'added' }),
            change('private', 'emit', 'sw-b', 'no-impact', { impact: null }),
            change('private', 'emit', 'sw-c', 'breaking-late', { impact: 'breaking' }),
            change('private', 'emit', 'sw-d', 'compatible-later', { impact: 'compatible' }),
        );
        const wrapper = mount(OverviewView, { props: { result } });

        await wrapper.findAll('.tiles').at(0)!.findAll('.tile').at(-1)!.trigger('click');

        expect(wrapper.findAll('[data-testid="change-row"] a').map((link) => link.text())).toEqual([
            'Shopware.Internal › secret',
            'sw-c › breaking-late',
            'sw-button-process › animationTimeout',
            'sw-d › compatible-later',
            'sw-a › added-first',
            'sw-b › no-impact',
        ]);
    });
});
