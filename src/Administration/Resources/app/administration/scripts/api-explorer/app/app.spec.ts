/**
 * @sw-package framework
 */

import { flushPromises, mount } from '@vue/test-utils';
import { productDetailFixture } from './__fixtures__/compare-fixture';
import { App } from './app';

const STATE = {
    status: {
        repoRoot: '/repo',
        adminRoot: '/repo/admin',
        branch: 'feature',
        head: null,
        changedFiles: 1,
        worktreeSnapshotCreatedAt: null,
    },
    refs: [],
    defaults: { base: 'trunk', head: 'WORKTREE' },
};

describe('api-explorer/app/app', () => {
    const originalFetch = global.fetch;
    let requests: string[] = [];

    beforeEach(() => {
        jest.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
        requests = [];
        global.fetch = ((url: string) => {
            requests.push(url);

            return Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve(url.startsWith('/api/state') ? STATE : productDetailFixture()),
            });
        }) as unknown as typeof fetch;
        window.location.hash = '#/components/sw-product-detail';
    });

    afterEach(() => {
        global.fetch = originalFetch;
        window.location.hash = '';
    });

    it('does not present changed sections as unchanged while the header search is active', async () => {
        const wrapper = mount(App);
        await flushPromises();

        await wrapper.find('header input[type="search"]').setValue('sw-product-detail');

        const methods = wrapper.find('section[aria-label="Methods"]');
        expect(methods.text()).toContain('1 · 1 changed');
        expect(methods.text()).toContain('onSave');
        expect(wrapper.text()).not.toMatch(/methods\s*0/);
        expect(wrapper.find('[data-block="sw_product_detail_actions_duplicate"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Switch to "Everything"');

        wrapper.unmount();
    });

    it('restores the comparison from a deep link and keeps it in every link', async () => {
        window.location.hash = '#/components/sw-product-detail?block=sw_product_detail&base=v6.7.15.1&head=WORKTREE';
        const wrapper = mount(App);

        expect(wrapper.find('[role="status"].loading').text()).toContain('Reading v6.7.15.1 and Local state');

        await flushPromises();

        expect(requests).toContain('/api/compare?base=v6.7.15.1&head=WORKTREE');
        expect(wrapper.find('.ref-chip').text()).toContain('v6.7.15.1');
        expect(wrapper.find('h1').text()).toBe('sw-product-detail');
        expect(wrapper.find('nav[aria-label="Categories"] a[href^="#/components?"]').attributes('href')).toBe(
            '#/components?base=v6.7.15.1&head=WORKTREE',
        );
        expect(wrapper.find('[data-testid="block-detail"] h3').text()).toBe('sw_product_detail');

        wrapper.unmount();
    });

    it('writes the default comparison into the URL and follows a swap', async () => {
        window.location.hash = '#/shopware?path=Shopware.Component';
        const wrapper = mount(App);
        await flushPromises();

        expect(window.location.hash).toBe('#/shopware?path=Shopware.Component&base=trunk&head=WORKTREE');

        await wrapper.find('button[aria-label="Swap base and comparison"]').trigger('click');
        await flushPromises();

        expect(window.location.hash).toBe('#/shopware?path=Shopware.Component&base=WORKTREE&head=trunk');
        expect(requests).toContain('/api/compare?base=WORKTREE&head=trunk');

        wrapper.unmount();
    });

    describe('as a static report', () => {
        beforeEach(() => {
            const { base, head, diff } = productDetailFixture();
            Object.assign(window, {
                __API_EXPLORER_REPORT__: {
                    base,
                    head,
                    diff,
                    worktreeCommit: 'abcdef1234567890abcdef1234567890abcdef12',
                    generatedAt: '2026-10-21T08:30:00.000Z',
                },
            });
        });

        afterEach(() => {
            delete (window as unknown as Record<string, unknown>).__API_EXPLORER_REPORT__;
        });

        it('shows the embedded comparison with fixed refs and makes no requests', async () => {
            window.location.hash = '#/components/sw-product-detail?base=v6.7.15.1&head=trunk';
            const wrapper = mount(App);
            await flushPromises();

            expect(requests).toEqual([]);
            expect(wrapper.find('h1').text()).toBe('sw-product-detail');
            expect(wrapper.find('header').text()).toContain('trunk');
            expect(wrapper.find('header').text()).toContain('Static report, generated 21 Oct 2026');
            expect(wrapper.find('header').text()).toContain('Checked-out commit @ abcdef1');
            expect(wrapper.find('header button').exists()).toBe(false);
            expect(wrapper.find('nav[aria-label="Categories"] a[href^="#/components"]').attributes('href')).toBe(
                '#/components',
            );
            expect(window.location.hash).toBe('#/components/sw-product-detail?base=v6.7.15.1&head=trunk');

            window.location.hash = '#/shopware?path=Shopware.Component';
            await flushPromises();

            expect(requests).toEqual([]);
            expect(wrapper.find('.loading').exists()).toBe(false);

            wrapper.unmount();
        });
    });
});
