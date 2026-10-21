/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { productDetailFixture } from '../__fixtures__/compare-fixture';
import { ShopwareObjectView } from '../views/shopware-object-view';
import { OverviewView } from '../views/overview-view';

const SHARED = Array.from({ length: 60 }, (_, index) => `field${index}: string`).join('; ');
const BEFORE = `(config: { ${SHARED}; flag: boolean }) => void`;
const AFTER = `(config: { ${SHARED}; flag: boolean; hideUpdateModule: boolean | null }) => void`;

function longResult() {
    const result = productDetailFixture();
    const register = result.diff.changes.find((change) => change.member === 'Shopware.Component.register')!;
    register.before = BEFORE;
    register.after = AFTER;
    result.base.shopwareObject.children[0].children[0].signature = BEFORE;
    result.head.shopwareObject.children[0].children[0].signature = AFTER;

    return result;
}

describe('api-explorer/app/components TypeDiff', () => {
    it('shows only the differing part of a long type pair in the change list, highlighted', () => {
        const wrapper = mount(OverviewView, { props: { result: longResult() } });
        const row = wrapper.findAll('[data-testid="change-row"]').find((entry) => entry.text().includes('register'))!;
        const diff = row.find('[data-testid="type-diff"]');

        expect(diff.findAll('.type-diff__added').map((part) => part.text())).toEqual([
            'added: ; hideUpdateModule: boolean | null',
        ]);
        expect(diff.find('.type-diff__removed').exists()).toBe(false);
        expect(diff.find('.type-diff__inline').text().length).toBeLessThan(140);
        expect(row.text()).not.toContain('field0: string');
    });

    it('highlights two changes far apart separately and collapses the unchanged text between them', () => {
        const before = `(app: { first: boolean; ${SHARED}; last: string }) => void`;
        const after = `(app: { first: boolean; hideUpdateModule?: boolean; ${SHARED}; last: string; hideUpdateModule: boolean | null }) => void`;
        const result = productDetailFixture();
        Object.assign(result.diff.changes.find((change) => change.member === 'Shopware.Component.register')!, {
            before,
            after,
        });
        const wrapper = mount(OverviewView, { props: { result } });
        const diff = wrapper.find('[data-testid="type-diff"]');

        expect(diff.findAll('.type-diff__added').map((part) => part.text())).toEqual([
            'added: hideUpdateModule?: boolean;',
            'added: ; hideUpdateModule: boolean | null',
        ]);
        expect(diff.findAll('.type-diff__gap')).toHaveLength(1);
        expect(diff.find('.type-diff__inline').text().length).toBeLessThan(260);
    });

    it('reveals the full before and after types on request', async () => {
        const wrapper = mount(ShopwareObjectView, { props: { result: longResult(), path: 'Shopware.Component.register' } });
        const diff = wrapper.find('[data-testid="object-detail"] [data-testid="type-diff"]');

        expect(diff.find('[data-testid="type-diff-full"]').exists()).toBe(false);

        await diff.find('button').trigger('click');

        expect(diff.find('button').text()).toBe('Hide full types');
        expect(diff.find('.signature--before').text()).toBe(BEFORE);
        expect(diff.find('.signature--after').text()).toBe(AFTER);
    });

    it('leaves short type pairs as they are', () => {
        const wrapper = mount(ShopwareObjectView, {
            props: { result: productDetailFixture(), path: 'Shopware.Component.register' },
        });
        const detail = wrapper.find('[data-testid="object-detail"]');

        expect(detail.find('[data-testid="type-diff"]').exists()).toBe(false);
        expect(detail.find('.signature--before').text()).toBe('() => void');
        expect(detail.find('.signature--after').text()).toBe('(name: string) => void');
    });
});
