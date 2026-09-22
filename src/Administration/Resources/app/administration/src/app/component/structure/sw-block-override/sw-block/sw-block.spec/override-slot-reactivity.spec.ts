/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils';
// Registers the `blockOverride` store as a side effect.
import '../../../../../store/block-override.store';
import createDataScopeFixture from '../../sw-block-override.spec/test-utils/create-data-scope-fixture';

/**
 * The scope of a scoped slot reaches the slot function as an argument, so it is invisible to the
 * reactivity system. `scoped-slot-reactivity.spec.ts` covers the case where the affected content is
 * the block's own default content. These cases cover the override path: the scope-dependent content
 * lives in a `<sw-block extends>` somewhere else in the tree, and the block that renders it has no
 * reactive link to that scope at all.
 */
async function mountWithOverride({
    name,
    overrideFirst = false,
    unmountable = false,
}: {
    name: string;
    overrideFirst?: boolean;
    unmountable?: boolean;
}): Promise<VueWrapper> {
    const swBlock = await wrapTestComponent('sw-block', { sync: true });

    const defaultBlock = `
        <sw-block name="${name}" :data="$dataScope">
            <span class="default-label">default</span>
        </sw-block>`;
    const override = `
        <scoped-host ${unmountable ? 'v-if="showOverride"' : ''} :label="label">
            <template #default="{ label: scopedLabel }">
                <sw-block extends="${name}">
                    <span class="override-label">{{ scopedLabel }}</span>
                </sw-block>
            </template>
        </scoped-host>`;

    const wrapper = mount(
        {
            template: `<div>${overrideFirst ? override + defaultBlock : defaultBlock + override}</div>`,
            components: {
                'sw-block': swBlock,
                'scoped-host': {
                    template: '<div class="scoped-host"><slot :label="label" /></div>',
                    props: ['label'],
                },
            },
            data() {
                return { label: 'before', showOverride: true };
            },
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
            },
        },
    );

    // The override registers itself while mounting, which invalidates the rendering block.
    await flushPromises();

    return wrapper;
}

describe('src/app/component/structure/sw-block-override/sw-block: override slot reactivity', () => {
    it.each([
        [
            'the default block is declared first',
            false,
        ],
        [
            'the override is declared first',
            true,
        ],
    ])('re-renders overridden content when the surrounding slot scope changes, %s', async (_label, overrideFirst) => {
        const wrapper = await mountWithOverride({
            name: `override-reactivity-${overrideFirst ? 'first' : 'last'}`,
            overrideFirst,
        });

        expect(wrapper.get('.override-label').text()).toBe('before');

        await wrapper.setData({ label: 'after' });
        await flushPromises();

        expect(wrapper.get('.override-label').text()).toBe('after');
    });

    it('unregisters an override whose slot function was replaced by a scope change', async () => {
        const wrapper = await mountWithOverride({
            name: 'override-unregister',
            unmountable: true,
        });

        expect(wrapper.get('.override-label').text()).toBe('before');

        // Vue hands the override a new slot function here. The registered entry must survive that,
        // otherwise the reference `removeBlock` filters by is dead and the override stays forever.
        await wrapper.setData({ label: 'after' });
        await flushPromises();

        await wrapper.setData({ showOverride: false });
        await flushPromises();

        expect(wrapper.find('.override-label').exists()).toBe(false);
        expect(wrapper.get('.default-label').text()).toBe('default');
    });
});
