/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import type CriteriaType from 'src/core/data/criteria.data';

const { Criteria } = Shopware.Data;

type RuleStub = { id: string; name: string; configHash?: string | null };

const duplicates: RuleStub[] = [
    { id: 'rule-b', name: 'Big cart (copy)' },
    { id: 'rule-c', name: 'Cart >= 100' },
];

async function createWrapper(rule: RuleStub, search = jest.fn(() => Promise.resolve(duplicates))) {
    const wrapper = mount(await wrapTestComponent('sw-settings-rule-duplicate-hint', { sync: true }), {
        props: { rule },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({ search }),
                },
            },
            mocks: {
                $tc: (key: string, count: number, values: { count: number }) => `${key} ${values.count}`,
                $router: {
                    resolve: ({ params }: { params: { id: string } }) => ({
                        href: `#/sw/settings/rule/detail/${params.id}/base`,
                    }),
                },
            },
            stubs: {
                'mt-banner': {
                    props: ['variant', 'title'],
                    template: '<div class="mt-banner" :data-variant="variant"><h3>{{ title }}</h3><slot /></div>',
                },
                'mt-link': {
                    props: ['href', 'target'],
                    template: '<a :href="href" :target="target"><slot /></a>',
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, search };
}

async function setRule(wrapper: Awaited<ReturnType<typeof createWrapper>>['wrapper'], rule: RuleStub) {
    await wrapper.setProps({ rule } as Record<string, unknown>);
}

describe('src/module/sw-settings-rule/component/sw-settings-rule-duplicate-hint', () => {
    it('does not search and shows nothing when the rule has no config hash', async () => {
        const { wrapper, search } = await createWrapper({ id: 'rule-a', name: 'Empty', configHash: null });

        expect(search).not.toHaveBeenCalled();
        expect(wrapper.find('.mt-banner').exists()).toBe(false);
    });

    it('shows nothing when no other rule has the same config hash', async () => {
        const { wrapper } = await createWrapper(
            { id: 'rule-a', name: 'Big cart', configHash: 'hash' },
            jest.fn(() => Promise.resolve([])),
        );

        expect(wrapper.find('.mt-banner').exists()).toBe(false);
    });

    it('searches other rules with the same config hash', async () => {
        const { search } = await createWrapper({ id: 'rule-a', name: 'Big cart', configHash: 'hash' });

        expect(search).toHaveBeenCalledTimes(1);

        const criteria = (search.mock.calls[0] as unknown as [CriteriaType])[0];
        expect(criteria.filters).toEqual([
            Criteria.equals('configHash', 'hash'),
            Criteria.not('AND', [Criteria.equals('id', 'rule-a')]),
        ]);
    });

    it('lists the duplicates in an info banner, linked in a new tab', async () => {
        const { wrapper } = await createWrapper({ id: 'rule-a', name: 'Big cart', configHash: 'hash' });

        const banner = wrapper.find('.mt-banner');
        expect(banner.attributes('data-variant')).toBe('info');
        expect(banner.text()).toContain('sw-settings-rule.detail.duplicateHint.title');

        const links = wrapper.findAll('a');
        expect(links.map((link) => link.text())).toEqual([
            'Big cart (copy)',
            'Cart >= 100',
        ]);
        expect(links[0].attributes('href')).toBe('#/sw/settings/rule/detail/rule-b/base');
        expect(links[0].attributes('target')).toBe('_blank');
    });

    it('names the number of duplicates beyond the listed ones', async () => {
        const { wrapper } = await createWrapper(
            { id: 'rule-a', name: 'Big cart', configHash: 'hash' },
            jest.fn(() => Promise.resolve(Object.assign([...duplicates], { total: 5 }))),
        );

        expect(wrapper.find('.sw-settings-rule-duplicate-hint__more').text()).toBe(
            'sw-settings-rule.detail.duplicateHint.more 3',
        );
    });

    it('does not mention more duplicates when all are listed', async () => {
        const { wrapper } = await createWrapper(
            { id: 'rule-a', name: 'Big cart', configHash: 'hash' },
            jest.fn(() => Promise.resolve(Object.assign([...duplicates], { total: 2 }))),
        );

        expect(wrapper.find('.mt-banner').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-rule-duplicate-hint__more').exists()).toBe(false);
    });

    it('reloads the duplicates when the config hash changes after saving', async () => {
        const { wrapper, search } = await createWrapper({ id: 'rule-a', name: 'Big cart', configHash: 'hash' });

        search.mockImplementation(() => Promise.resolve([]));
        await setRule(wrapper, { id: 'rule-a', name: 'Big cart', configHash: 'other-hash' });
        await flushPromises();

        expect(search).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.mt-banner').exists()).toBe(false);
    });

    it('ignores the result of an outdated search that resolves last', async () => {
        const pending: Array<(result: RuleStub[]) => void> = [];
        const search = jest.fn(
            () =>
                new Promise<RuleStub[]>((resolve) => {
                    pending.push(resolve);
                }),
        );
        const { wrapper } = await createWrapper({ id: 'rule-a', name: 'Big cart', configHash: 'hash' }, search);

        await setRule(wrapper, { id: 'rule-x', name: 'Logged in', configHash: 'other-hash' });
        await flushPromises();

        pending[1]([{ id: 'rule-y', name: 'Logged in (copy)' }]);
        await flushPromises();
        pending[0](duplicates);
        await flushPromises();

        expect(wrapper.findAll('a').map((link) => link.text())).toEqual(['Logged in (copy)']);
    });

    it('reloads the duplicates when switching to a rule with the same config hash', async () => {
        const { wrapper, search } = await createWrapper({ id: 'rule-a', name: 'Big cart', configHash: 'hash' });

        await setRule(wrapper, { id: 'rule-b', name: 'Big cart (copy)', configHash: 'hash' });
        await flushPromises();

        expect(search).toHaveBeenCalledTimes(2);

        const criteria = (search.mock.calls[1] as unknown as [CriteriaType])[0];
        expect(criteria.filters).toContainEqual(Criteria.not('AND', [Criteria.equals('id', 'rule-b')]));
    });
});
