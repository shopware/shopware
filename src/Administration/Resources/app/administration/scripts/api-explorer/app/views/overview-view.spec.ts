/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { productDetailFixture } from '../__fixtures__/compare-fixture';
import { OverviewView } from './overview-view';

function mountOverview() {
    return mount(OverviewView, { props: { result: productDetailFixture(), query: '' } });
}

function rowTexts(wrapper: ReturnType<typeof mountOverview>): string[] {
    return wrapper.findAll('[data-testid="change-row"]').map((row) => row.text());
}

describe('api-explorer/app/views/overview-view', () => {
    it('shows the breaking banner and every change by default', () => {
        const wrapper = mountOverview();

        expect(wrapper.find('#status-title').text()).toBe('2 breaking changes without deprecation');
        expect(rowTexts(wrapper)).toHaveLength(7);
    });

    it('filters the change list by the selected severity tile and clears it on a second click', async () => {
        const wrapper = mountOverview();
        const tile = wrapper.findAll('.tile').find((button) => button.text().includes('Potentially breaking'))!;

        await tile.trigger('click');

        expect(tile.attributes('aria-pressed')).toBe('true');
        expect(rowTexts(wrapper)).toEqual([
            expect.stringContaining('sw_product_detail_actions_duplicate'),
            expect.stringContaining('Shopware.Component › register'),
        ]);

        await tile.trigger('click');

        expect(rowTexts(wrapper)).toHaveLength(7);
    });

    it('limits the list to breaking changes from the banner and hides private changes on request', async () => {
        const wrapper = mountOverview();

        await wrapper.find('.banner button').trigger('click');
        expect(rowTexts(wrapper)).toHaveLength(2);
        expect(rowTexts(wrapper).every((text) => text.includes('Breaking'))).toBe(true);

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Show all')!
            .trigger('click');
        await wrapper.find('input[type="checkbox"]').setValue(false);

        expect(rowTexts(wrapper)).toHaveLength(5);
        expect(rowTexts(wrapper).some((text) => text.includes('Private'))).toBe(false);
    });

    it('filters the change list by the search query from the header', async () => {
        const wrapper = mountOverview();

        await wrapper.setProps({ query: 'component.reg' });

        expect(rowTexts(wrapper)).toEqual([expect.stringContaining('register')]);
    });

    it('links changes to their detail views', () => {
        const wrapper = mountOverview();
        const hrefs = wrapper.findAll('[data-testid="change-row"] a').map((link) => link.attributes('href'));

        expect(hrefs).toContain('#/components/sw-product-detail?block=sw_product_detail_actions_duplicate');
        expect(hrefs).toContain('#/shopware?path=Shopware.Component.register');
    });

    it('counts what the tool read from the head snapshot', () => {
        const wrapper = mountOverview();
        const cards = wrapper.findAll('[data-testid="coverage-card"]').map((card) => card.text());

        expect(cards).toEqual([
            expect.stringContaining('6 entries · 3 changes'),
            expect.stringContaining('2 components, 1 of them private · 2 changes'),
            expect.stringContaining('3 blocks with hierarchy and content · 2 changes'),
        ]);
    });
});
