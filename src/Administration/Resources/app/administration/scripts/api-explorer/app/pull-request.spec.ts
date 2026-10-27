/**
 * @sw-package framework
 */

import { flushPromises, mount } from '@vue/test-utils';
import type { PullRequestResponse } from '../server/api-types';
import { productDetailFixture } from './__fixtures__/compare-fixture';
import { App } from './app';
import { hrefFor, parseHash } from './router';

const HEAD_SHA = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';
const MERGE_BASE_SHA = 'c0ffee00112233445566778899aabbccddeeff00';

const PULL_REQUEST: PullRequestResponse = {
    number: 19666,
    owner: 'shopware',
    repo: 'shopware',
    title: 'feat: rework the Shopware update module',
    state: 'closed',
    merged: true,
    url: 'https://github.com/shopware/shopware/pull/19666',
    baseRef: 'trunk',
    headSha: HEAD_SHA,
    mergeBaseSha: MERGE_BASE_SHA,
    warnings: ['GitHub reports head 1234567, fetched a1b2c3d.'],
    refs: { base: 'pr-base:19666', head: 'pr:19666' },
};

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

function compareResponse(base: string, head: string) {
    const fixture = productDetailFixture();
    const commitOf = (ref: string) => {
        if (ref.startsWith('pr-base:')) {
            return MERGE_BASE_SHA;
        }

        return ref.startsWith('pr:') ? HEAD_SHA : fixture.base.commit;
    };

    return {
        ...fixture,
        base: { ...fixture.base, ref: base, commit: commitOf(base) },
        head: { ...fixture.head, ref: head, commit: commitOf(head) },
        diff: {
            ...fixture.diff,
            base: { ...fixture.diff.base, ref: base, commit: commitOf(base) },
            head: { ...fixture.diff.head, ref: head, commit: commitOf(head) },
        },
    };
}

type Answer = { status: number; body: unknown } | Error;

describe('api-explorer/app pull requests', () => {
    const originalFetch = global.fetch;
    let requests: string[];
    let answerPullRequest: (url: URL) => Answer | Promise<Answer>;

    beforeEach(() => {
        jest.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
        requests = [];
        answerPullRequest = () => ({ status: 200, body: PULL_REQUEST });
        global.fetch = (async (input: string) => {
            requests.push(input);
            const url = new URL(input, 'http://localhost');
            let answer: Answer = { status: 200, body: STATE };

            if (url.pathname === '/api/pull-request') {
                answer = await answerPullRequest(url);
            } else if (url.pathname === '/api/compare') {
                answer = {
                    status: 200,
                    body: compareResponse(url.searchParams.get('base') ?? '', url.searchParams.get('head') ?? ''),
                };
            }

            if (answer instanceof Error) {
                throw answer;
            }

            const { status, body } = answer;

            return { ok: status < 400, status, json: () => Promise.resolve(body) };
        }) as unknown as typeof fetch;
    });

    afterEach(() => {
        global.fetch = originalFetch;
        window.location.hash = '';
    });

    it('keeps pull request refs in the URL', () => {
        const comparison = { base: 'pr-base:19666', head: 'pr:shopware/shopware/19666' };
        const href = hrefFor({ name: 'component', component: 'sw-settings', block: null }, comparison);

        expect(parseHash(href)).toEqual({
            route: { name: 'component', component: 'sw-settings', block: null },
            comparison,
        });
    });

    it('opens a deep link to a pull request and describes both sides without the raw refs', async () => {
        window.location.hash = '#/?base=pr-base:19666&head=pr:19666';
        const wrapper = mount(App);
        await flushPromises();

        expect(requests.filter((url) => !url.startsWith('/api/state'))).toEqual([
            '/api/pull-request?pr=19666',
            '/api/compare?base=pr-base%3A19666&head=pr%3A19666',
        ]);

        const header = wrapper.find('header');
        const link = header.find('a[data-testid="pull-request-link"]');
        expect(link.text()).toBe('PR #19666 · feat: rework the Shopware update module');
        expect(link.attributes('href')).toBe('https://github.com/shopware/shopware/pull/19666');
        expect(header.text()).toContain('merge base with trunk @ c0ffee0');
        expect(header.text()).toContain('PR head @ a1b2c3d');
        expect(header.text()).not.toMatch(/pr(-base)?:/);

        const subtitle = wrapper.find('.page__header p');
        expect(subtitle.find('a').attributes('href')).toBe('https://github.com/shopware/shopware/pull/19666');
        expect(subtitle.text()).toBe(
            'PR #19666 · feat: rework the Shopware update module: PR head @ a1b2c3d, compared to merge base with trunk @ c0ffee0.',
        );

        expect(parseHash(window.location.hash).comparison).toEqual({ base: 'pr-base:19666', head: 'pr:19666' });

        wrapper.unmount();
    });

    it('says what it is doing while the pull request and its refs are read', async () => {
        let release: () => void = () => undefined;
        answerPullRequest = () =>
            new Promise((resolve) => {
                release = () => resolve({ status: 200, body: PULL_REQUEST });
            });
        window.location.hash = '#/?base=pr-base:19666&head=pr:19666';
        const wrapper = mount(App);
        await flushPromises();

        expect(wrapper.find('.loading').text()).toContain('Fetching PR #19666 from GitHub …');

        release();
        await flushPromises();

        expect(wrapper.find('.loading').exists()).toBe(false);
        expect(wrapper.find('header').text()).toContain('PR head @ a1b2c3d');

        wrapper.unmount();
    });

    it('fetches the pull request again before comparing on refresh', async () => {
        window.location.hash = '#/?base=pr-base:19666&head=pr:19666';
        const wrapper = mount(App);
        await flushPromises();
        requests = [];

        await wrapper
            .findAll('header button')
            .find((button) => button.text() === 'Refresh')!
            .trigger('click');
        await flushPromises();

        expect(requests.filter((url) => !url.startsWith('/api/state'))).toEqual([
            '/api/pull-request?pr=19666&refresh=1',
            '/api/compare?base=pr-base%3A19666&head=pr%3A19666&refresh=1',
        ]);

        wrapper.unmount();
    });

    it('loads a pull request in the comparison dialog and compares it', async () => {
        window.location.hash = '#/?base=trunk&head=WORKTREE';
        const wrapper = mount(App, { attachTo: document.body });
        await flushPromises();

        await wrapper.find('header .ref-chip').trigger('click');
        await wrapper.find('input[aria-label="Pull request"]').setValue('https://github.com/shopware/shopware/pull/19666');
        await wrapper
            .findAll('.dialog button')
            .find((button) => button.text() === 'Load')!
            .trigger('click');
        await flushPromises();

        expect(requests).toContain(
            `/api/pull-request?pr=${encodeURIComponent('https://github.com/shopware/shopware/pull/19666')}`,
        );
        const card = wrapper.find('[data-testid="pull-request-card"]');
        expect(card.text()).toContain('PR #19666 · feat: rework the Shopware update module');
        expect(card.text()).toContain('merged');
        expect(card.text()).toContain('into trunk');
        expect(card.text()).toContain('head a1b2c3d');
        expect(card.text()).toContain('merge base c0ffee0');
        expect(card.text()).toContain('GitHub reports head 1234567, fetched a1b2c3d.');

        await card.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.dialog').exists()).toBe(false);
        expect(parseHash(window.location.hash).comparison).toEqual({ base: 'pr-base:19666', head: 'pr:19666' });
        expect(requests).toContain('/api/compare?base=pr-base%3A19666&head=pr%3A19666');
        expect(wrapper.find('header').text()).toContain('PR #19666 · feat: rework the Shopware update module');

        wrapper.unmount();
    });

    it.each([
        [
            'an unknown pull request',
            () => ({ status: 400, body: { error: 'Unknown pull request #8 of shopware/shopware' } }),
            'There is no pull request #8 in shopware/shopware.',
        ],
        [
            'a failed download',
            () => ({
                status: 500,
                body: { error: 'Fetching pull request #8 of shopware/shopware failed: fatal: unable to access github.com' },
            }),
            'PR #8 could not be downloaded from GitHub. Check the network connection and try again.',
        ],
        [
            'an unreachable server',
            () => new TypeError('Failed to fetch'),
            'The explorer server cannot be reached. Check that it is still running.',
        ],
    ])('explains %s in the dialog', async (_case, answer, message) => {
        window.location.hash = '#/?base=trunk&head=WORKTREE';
        const wrapper = mount(App);
        await flushPromises();
        answerPullRequest = answer;

        await wrapper.find('header .ref-chip').trigger('click');
        await wrapper.find('input[aria-label="Pull request"]').setValue('#8');
        await wrapper.find('input[aria-label="Pull request"]').trigger('keydown', { key: 'Enter' });
        await flushPromises();

        expect(wrapper.find('.dialog [role="alert"]').text()).toContain(message);
        expect(wrapper.find('[data-testid="pull-request-card"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('explains a failing pull request instead of comparing', async () => {
        answerPullRequest = () => ({ status: 400, body: { error: 'Unknown pull request #8 of shopware/shopware' } });
        window.location.hash = '#/?base=pr-base:8&head=pr:8';
        const wrapper = mount(App);
        await flushPromises();

        expect(wrapper.find('.alert').text()).toContain('The pull request could not be loaded');
        expect(wrapper.find('.alert').text()).toContain('There is no pull request #8 in shopware/shopware.');
        expect(requests.some((url) => url.startsWith('/api/compare'))).toBe(false);

        wrapper.unmount();
    });
});
