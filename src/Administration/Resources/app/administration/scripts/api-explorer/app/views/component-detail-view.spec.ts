/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { productDetailFixture } from '../__fixtures__/compare-fixture';
import { ComponentDetailView } from './component-detail-view';

function mountDetail(block: string | null = null) {
    return mount(ComponentDetailView, {
        props: { result: productDetailFixture(), name: 'sw-product-detail', block },
    });
}

function treeRows(wrapper: ReturnType<typeof mountDetail>) {
    return wrapper.findAll('[data-block]');
}

describe('api-explorer/app/views/component-detail-view', () => {
    it('shows a moved block with its parent chain before and after', () => {
        const wrapper = mountDetail('sw_product_detail_actions_duplicate');
        const chains = wrapper.find('[data-testid="parent-chains"]');

        expect(wrapper.find('[data-testid="block-detail"] h3').text()).toBe('sw_product_detail_actions_duplicate');
        expect(
            chains
                .find('.chain--before')
                .findAll('.chain__item')
                .map((item) => item.text()),
        ).toEqual([
            'sw_product_detail',
            'sw_product_detail_actions',
            'sw_product_detail_actions_menu',
            'sw_product_detail_actions_duplicate',
        ]);
        expect(
            chains
                .find('.chain--after')
                .findAll('.chain__item')
                .map((item) => item.text()),
        ).toEqual([
            'sw_product_detail',
            'sw_product_detail_actions',
            'sw_product_detail_actions_duplicate',
        ]);
        expect(chains.text()).toContain('Before (trunk)');
        expect(chains.text()).toContain('After (local)');
    });

    it('merges the block trees, keeps removed blocks at their base position and strikes them through', () => {
        const wrapper = mountDetail();
        const rows = treeRows(wrapper);

        expect(rows.map((row) => row.attributes('data-block'))).toEqual([
            'sw_product_detail',
            'sw_product_detail_actions',
            'sw_product_detail/sw_product_detail_actions/sw_product_detail_actions_menu',
            'sw_product_detail_actions_duplicate',
        ]);
        expect(rows[2].find('.removed').exists()).toBe(true);
        expect(rows[3].find('.removed').exists()).toBe(false);
    });

    it('shows a line diff of the selected block content', async () => {
        const wrapper = mountDetail();

        await treeRows(wrapper)
            .find((row) => row.attributes('data-block') === 'sw_product_detail_actions_duplicate')!
            .trigger('click');

        const lines = wrapper.findAll('.code-diff__line').map((line) => [
            line.attributes('data-type'),
            line.find('.code-diff__text').text(),
        ]);
        expect(lines).toEqual([
            [
                'removed',
                '<button>Duplicate</button>',
            ],
            [
                'added',
                '<button>',
            ],
            [
                'added',
                'Duplicate',
            ],
            [
                'added',
                '</button>',
            ],
        ]);
    });

    it('does not show parent chains for a block that stayed in place', async () => {
        const wrapper = mountDetail();

        await treeRows(wrapper)
            .find((row) => row.text().includes('sw_product_detail_actions_menu'))!
            .trigger('click');

        expect(wrapper.find('[data-testid="block-detail"]').text()).toContain('Block removed.');
        expect(wrapper.find('[data-testid="parent-chains"]').exists()).toBe(false);
    });

    it('lists changed members with before, after and the reasons', () => {
        const wrapper = mountDetail();

        expect(wrapper.findAll('[data-testid="member-row"]').map((row) => row.text())).toEqual([
            expect.stringMatching(/Breaking.*onSave.*\(\) => void.*—.*Removed without prior deprecation\./),
        ]);
    });
});
