/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package framework
 * @group disabledCompat
 */

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * HOW TO WRITE THESE TESTS
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * ## What is under test
 *
 * The Twig → Native Block Runtime Adapter bridges legacy Twig block overrides
 * (registered via `Shopware.Component.override()`) with components that have been
 * migrated from `{% block %}` to `<sw-block name="...">`. Plugin developers must not
 * need to change their existing overrides; the shim activates automatically.
 *
 * These are end-to-end integration tests. They verify the full pipeline:
 *
 *   Shopware.Component.override({ template: '{% block ... %}' })
 *     → indexTwigBlocksFromTemplate()                  [boot time]
 *       → sw-block mounts with matching `name` prop    [runtime]
 *         → shim slot injected into block context
 *           → DOM output matches expected override content
 *
 *
 * ─── Philosophy: test observable behavior, not internals ────────────────────
 *
 * These tests MUST only assert on:
 *   - Rendered DOM (wrapper.find(...), wrapper.text(), CSS selectors)
 *   - Side-effects visible from the outside (console.warn deprecation messages)
 *
 * These tests MUST NOT directly import or assert on:
 *   - twig-block-index.ts     (blockIndex Map, getBlockEntries, hasBlockEntries)
 *   - reconstruct-twig-template.ts (reconstructInnerTemplate)
 *   - create-shim-slot.ts     (createShimSlot, renderFnCache, warnedBlocks)
 *
 * If you feel the need to inspect internal state, write an additional behavioral
 * test that observes the same property through rendered output instead.
 *
 *
 * ─── Setup pattern ──────────────────────────────────────────────────────────
 *
 * 1.  Register a Twig block override BEFORE mounting by calling:
 *
 *         Shopware.Component.override('sw-some-component', {
 *             template: `
 *                 {% block block_name %}
 *                     <div class="override-content">…</div>
 *                 {% endblock %}
 *             `,
 *         });
 *
 *     This replicates what a plugin does at boot time. The hook added to
 *     `async-component.factory.ts` feeds the template into
 *     `indexTwigBlocksFromTemplate()` at that same moment — no extra test
 *     setup is required.
 *
 * 2.  Mount a Vue component whose template contains a migrated native block:
 *
 *         <sw-block name="block_name" :data="$dataScope()">
 *             <div class="default-content">…</div>
 *         </sw-block>
 *
 *     Resolve components via `wrapTestComponent('sw-block', { sync: true })` and
 *     `wrapTestComponent('sw-block-parent', { sync: true })`.
 *     Provide `$dataScope` via `global.mocks` using the `getBlockDataScope` helper
 *     (imported from `../sw-block/get-block-data-scope`), exactly as
 *     `sw-block.spec.js` does. This ensures the host component's reactive proxy is
 *     exposed to the shim slot under the same conditions as production.
 *
 * 3.  Assert on the rendered DOM with `wrapper.find(...)`.
 *
 *
 * ─── State isolation between tests ──────────────────────────────────────────
 *
 * The block index (Map) and the deprecation-warning deduplication Set are
 * module-level singletons. Call `resetBlockIndex()` and `resetShimSlotState()`
 * in `afterEach` to guarantee a clean slate regardless of test order.
 *
 * Additionally, use a UNIQUE block name per test (e.g. embed a test-local
 * identifier in the block name: "shim_test_basic_rendering_no_parent") so tests
 * cannot share index entries.
 *
 * Note: importing `resetBlockIndex` and `resetShimSlotState` is the only
 * permissible reference to shim-internal exports. They are test seams, not
 * assertion targets.
 *
 *
 * ─── Deprecation warning assertions ─────────────────────────────────────────
 *
 * `console.warn` is spied on globally in `beforeEach` to suppress noise in test
 * output and to allow assertions in the deprecation describe block. The spy is
 * stored in `consoleSpy` and restored in `afterEach`.
 *
 * Assert that the warning message contains the block name and the migration hint
 * string (`<sw-block extends="…">`). Do NOT assert on internal function names or
 * file paths embedded in the message.
 *
 *
 * ─── Override registration timing ───────────────────────────────────────────
 *
 * Call `Shopware.Component.override(…)` at the TOP of each test (or inside
 * `beforeEach`), BEFORE `mount()`. This faithfully mirrors the boot sequence
 * where plugin overrides are registered before any Vue component mounts, and
 * before any `sw-block` with a matching name has had a chance to execute
 * `hasBlockEntries()`.
 *
 *
 * ─── What NOT to test ────────────────────────────────────────────────────────
 *
 *   ✗ That `renderFnCache` avoids double compilation (internal optimization).
 *   ✗ The token structure returned by TwigJS (internal AST detail).
 *   ✗ That `blockIndex` contains specific Map entries after override registration.
 *   ✗ Internal function signatures of shim helpers.
 *   ✗ The number of times `compileToFunction` is called.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 */

import { mount } from '@vue/test-utils';
import { resetBlockIndex } from 'src/core/factory/twig-block-index';
import '../../../../store/block-override.store';
import getBlockDataScope from '../sw-block/get-block-data-scope';
import { resetShimSlotState } from './create-shim-slot';

/**
 * Mounts a host component containing a single `<sw-block name="...">` wrapped in
 * `.component-root`. The host component is conditionally rendered via
 * `v-if="renderHost"` so lifecycle tests can toggle it without re-creating the
 * wrapper instance.
 *
 * Additional native `<sw-block extends="...">` elements (for interop tests) can
 * be injected via `nativeExtensions` and are rendered as siblings outside
 * `.component-root`, matching how plugin extension components are structured in
 * production.
 */
async function createWrapper({
    blockName = 'shim-test-block',
    defaultContent = '<div class="default-content"></div>',
    nativeExtensions = '',
    extraData = {},
    extraOptions = {},
    renderHost = true,
} = {}) {
    const swBlock = await wrapTestComponent('sw-block', { sync: true });
    const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });

    return mount(
        {
            template: `
                <div>
                    <div v-if="renderHost" class="component-root">
                        <sw-block name="${blockName}" :data="$dataScope()">
                            ${defaultContent}
                        </sw-block>
                    </div>
                    ${nativeExtensions}
                </div>
            `,
            data() {
                return {
                    renderHost,
                    ...extraData,
                };
            },
            ...extraOptions,
        },
        {
            global: {
                mocks: {
                    $dataScope: getBlockDataScope,
                },
                components: {
                    'sw-block': swBlock,
                    'sw-block-parent': swBlockParent,
                },
            },
        },
    );
}

type MultiBlockWrapperConfig = {
    rootClass: string;
    blockName: string;
    defaultContent: string;
};

async function createMultiBlockWrapper(blocks: MultiBlockWrapperConfig[]) {
    const swBlock = await wrapTestComponent('sw-block', { sync: true });
    const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });

    return mount(
        {
            template: `
                <div>
                    ${blocks
                        .map(
                            ({ rootClass, blockName, defaultContent }) => `
                                <div class="${rootClass}">
                                    <sw-block name="${blockName}" :data="$dataScope()">
                                        ${defaultContent}
                                    </sw-block>
                                </div>
                            `,
                        )
                        .join('')}
                </div>
            `,
        },
        {
            global: {
                mocks: {
                    $dataScope: getBlockDataScope,
                },
                components: {
                    'sw-block': swBlock,
                    'sw-block-parent': swBlockParent,
                },
            },
        },
    );
}

describe('Twig → Native Block Runtime Adapter (shim)', () => {
    let consoleSpy: jest.SpyInstance;

    beforeEach(() => {
        consoleSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        consoleSpy.mockRestore();
        resetBlockIndex();
        resetShimSlotState();
    });

    // ─── Basic rendering ─────────────────────────────────────────────────────

    describe('basic rendering', () => {
        it('replaces the entire default block content when the Twig override contains no {% parent %}', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_basic_replaces_default %}
                        <div class="override-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_basic_replaces_default' });

            expect(wrapper.find('.default-content').exists()).toBeFalsy();
            expect(wrapper.find('.override-content').exists()).toBeTruthy();
        });

        it('leaves the default block content untouched when no Twig override targets that block name', async () => {
            const wrapper = await createWrapper({ blockName: 'shim_basic_no_override' });

            expect(wrapper.find('.default-content').exists()).toBeTruthy();
        });

        it('renders nothing when the Twig override block body is empty and there is no default content', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_basic_empty_body %}{% endblock %}`,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_basic_empty_body',
                defaultContent: '',
            });

            expect(wrapper.findAll('.component-root > *')).toHaveLength(0);
        });
    });

    // ─── {% parent %} support ────────────────────────────────────────────────

    describe('{% parent %} support', () => {
        it('renders the default content BEFORE the override when {% parent %} is placed first', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_parent_first %}
                        {% parent %}
                        <div class="override-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_parent_first' });

            expect(wrapper.find('.default-content + .override-content').exists()).toBeTruthy();
            expect(wrapper.find('.override-content + .default-content').exists()).toBeFalsy();
        });

        it('renders the default content AFTER the override when {% parent %} is placed last', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_parent_last %}
                        <div class="override-content"></div>
                        {% parent %}
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_parent_last' });

            expect(wrapper.find('.override-content + .default-content').exists()).toBeTruthy();
            expect(wrapper.find('.default-content + .override-content').exists()).toBeFalsy();
        });

        it('renders a Twig override with only {% parent %} as equivalent to the default block content', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_parent_only %}{% parent %}{% endblock %}`,
            });

            const wrapper = await createWrapper({ blockName: 'shim_parent_only' });

            expect(wrapper.find('.default-content').exists()).toBeTruthy();
        });
    });

    describe('multiple Twig overrides for the same block', () => {
        it('stacks multiple Twig overrides in registration order when each uses {% parent %}', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_multi_same_with_parent %}
                        {% parent %}
                        <div class="override-content-1"></div>
                    {% endblock %}
                `,
            });

            Shopware.Component.override('sw-product-list', {
                template: `
                    {% block shim_multi_same_with_parent %}
                        {% parent %}
                        <div class="override-content-2"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_multi_same_with_parent' });

            expect(wrapper.find('.default-content').exists()).toBeTruthy();
            expect(wrapper.find('.override-content-1').exists()).toBeTruthy();
            expect(wrapper.find('.override-content-2').exists()).toBeTruthy();
            expect(wrapper.find('.default-content + .override-content-1 + .override-content-2').exists()).toBeTruthy();
        });

        it('renders only the last registered Twig override when none of the overrides use {% parent %}', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_multi_same_no_parent %}
                        <div class="override-content-1"></div>
                    {% endblock %}
                `,
            });

            Shopware.Component.override('sw-product-list', {
                template: `
                    {% block shim_multi_same_no_parent %}
                        <div class="override-content-2"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_multi_same_no_parent' });

            expect(wrapper.find('.override-content-1').exists()).toBeFalsy();
            expect(wrapper.find('.override-content-2').exists()).toBeTruthy();
        });

        it('handles a mix of overrides where only the last override uses {% parent %}', async () => {
            // Override 1: no parent → replaces default
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_multi_same_mixed %}
                        <div class="override-content-1"></div>
                    {% endblock %}
                `,
            });

            // Override 2: with parent → override-1 is "parent" from its perspective
            Shopware.Component.override('sw-product-list', {
                template: `
                    {% block shim_multi_same_mixed %}
                        {% parent %}
                        <div class="override-content-2"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_multi_same_mixed' });

            // override-1 is the parent of override-2; default is below override-1 but override-1 has no parent
            expect(wrapper.find('.default-content').exists()).toBeFalsy();
            expect(wrapper.find('.override-content-1').exists()).toBeTruthy();
            expect(wrapper.find('.override-content-2').exists()).toBeTruthy();
            expect(wrapper.find('.override-content-1 + .override-content-2').exists()).toBeTruthy();
        });
    });

    // ─── Multiple overrides targeting different blocks ────────────────────────

    describe('multiple Twig overrides targeting different block names', () => {
        it('applies each Twig override independently to its own sw-block without cross-contamination', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_multi_diff_block_a %}<div class="override-a"></div>{% endblock %}
                    {% block shim_multi_diff_block_b %}<div class="override-b"></div>{% endblock %}
                `,
            });

            const swBlock = await wrapTestComponent('sw-block', { sync: true });
            const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });
            const wrapper = mount(
                {
                    template: `
                        <div>
                            <div class="root-a">
                                <sw-block name="shim_multi_diff_block_a" :data="$dataScope()">
                                    <div class="default-a"></div>
                                </sw-block>
                            </div>
                            <div class="root-b">
                                <sw-block name="shim_multi_diff_block_b" :data="$dataScope()">
                                    <div class="default-b"></div>
                                </sw-block>
                            </div>
                        </div>
                    `,
                },
                {
                    global: {
                        mocks: { $dataScope: getBlockDataScope },
                        components: {
                            'sw-block': swBlock,
                            'sw-block-parent': swBlockParent,
                        },
                    },
                },
            );

            expect(wrapper.find('.root-a .override-a').exists()).toBeTruthy();
            expect(wrapper.find('.root-a .default-a').exists()).toBeFalsy();
            expect(wrapper.find('.root-b .override-b').exists()).toBeTruthy();
            expect(wrapper.find('.root-b .default-b').exists()).toBeFalsy();
            expect(wrapper.find('.root-a .override-b').exists()).toBeFalsy();
            expect(wrapper.find('.root-b .override-a').exists()).toBeFalsy();
        });

        it('does not apply an override registered for block-A to a sw-block with name block-B', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_cross_contamination_block_a %}
                        <div class="override-a"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_cross_contamination_block_b',
                defaultContent: '<div class="default-b"></div>',
            });

            expect(wrapper.find('.override-a').exists()).toBeFalsy();
            expect(wrapper.find('.default-b').exists()).toBeTruthy();
        });
    });

    // ─── Combinations: multiple plugins × multiple blocks ────────────────────

    describe('combinations of multiple plugins overriding multiple blocks', () => {
        it('stacks three plugins that all target the same block with {% parent %} in registration order', async () => {
            // Simulates three independent plugins each appending content below the previous layer.
            Shopware.Component.override('sw-plugin-a', {
                template: `
                    {% block shim_combo_three_plugins %}
                        {% parent %}
                        <div class="plugin-a-content"></div>
                    {% endblock %}
                `,
            });

            Shopware.Component.override('sw-plugin-b', {
                template: `
                    {% block shim_combo_three_plugins %}
                        {% parent %}
                        <div class="plugin-b-content"></div>
                    {% endblock %}
                `,
            });

            Shopware.Component.override('sw-plugin-c', {
                template: `
                    {% block shim_combo_three_plugins %}
                        {% parent %}
                        <div class="plugin-c-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_combo_three_plugins' });

            // Full chain: default → plugin-a → plugin-b → plugin-c
            expect(
                wrapper.find('.default-content + .plugin-a-content + .plugin-b-content + .plugin-c-content').exists(),
            ).toBeTruthy();
        });

        it('stacks two plugins independently on two shared blocks without cross-contamination', async () => {
            // Plugin A overrides both block-X and block-Y.
            Shopware.Component.override('sw-plugin-a', {
                template: `
                    {% block shim_combo_shared_block_x %}
                        {% parent %}
                        <div class="plugin-a-x"></div>
                    {% endblock %}
                    {% block shim_combo_shared_block_y %}
                        {% parent %}
                        <div class="plugin-a-y"></div>
                    {% endblock %}
                `,
            });

            // Plugin B also overrides both blocks.
            Shopware.Component.override('sw-plugin-b', {
                template: `
                    {% block shim_combo_shared_block_x %}
                        {% parent %}
                        <div class="plugin-b-x"></div>
                    {% endblock %}
                    {% block shim_combo_shared_block_y %}
                        {% parent %}
                        <div class="plugin-b-y"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createMultiBlockWrapper([
                {
                    rootClass: 'root-x',
                    blockName: 'shim_combo_shared_block_x',
                    defaultContent: '<div class="default-x"></div>',
                },
                {
                    rootClass: 'root-y',
                    blockName: 'shim_combo_shared_block_y',
                    defaultContent: '<div class="default-y"></div>',
                },
            ]);

            // block-x: default → plugin-a-x → plugin-b-x
            expect(wrapper.find('.root-x .default-x + .plugin-a-x + .plugin-b-x').exists()).toBeTruthy();

            // block-y: default → plugin-a-y → plugin-b-y (independent of block-x)
            expect(wrapper.find('.root-y .default-y + .plugin-a-y + .plugin-b-y').exists()).toBeTruthy();
        });

        it('stacks plugin-A on both blocks, plugin-B only on block-X, leaving block-Y untouched by plugin-B', async () => {
            // Plugin A overrides both blocks.
            Shopware.Component.override('sw-plugin-a', {
                template: `
                    {% block shim_combo_partial_block_x %}
                        {% parent %}
                        <div class="plugin-a-x"></div>
                    {% endblock %}
                    {% block shim_combo_partial_block_y %}
                        {% parent %}
                        <div class="plugin-a-y"></div>
                    {% endblock %}
                `,
            });

            // Plugin B only overrides block-X.
            Shopware.Component.override('sw-plugin-b', {
                template: `
                    {% block shim_combo_partial_block_x %}
                        {% parent %}
                        <div class="plugin-b-x"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createMultiBlockWrapper([
                {
                    rootClass: 'root-x',
                    blockName: 'shim_combo_partial_block_x',
                    defaultContent: '<div class="default-x"></div>',
                },
                {
                    rootClass: 'root-y',
                    blockName: 'shim_combo_partial_block_y',
                    defaultContent: '<div class="default-y"></div>',
                },
            ]);

            // block-x gets both plugins stacked
            expect(wrapper.find('.root-x .default-x + .plugin-a-x + .plugin-b-x').exists()).toBeTruthy();

            // block-y gets only plugin-A; plugin-B must not appear here
            expect(wrapper.find('.root-y .default-y + .plugin-a-y').exists()).toBeTruthy();
            expect(wrapper.find('.root-y .plugin-b-x').exists()).toBeFalsy();
        });

        it('applies all blocks from two separate override calls for the same component simultaneously', async () => {
            // Two separate override() calls for the same component name, each with
            // a different block. Both should be indexed and applied independently.
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_combo_two_calls_block_x %}<div class="override-x"></div>{% endblock %}`,
            });

            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_combo_two_calls_block_y %}<div class="override-y"></div>{% endblock %}`,
            });

            const wrapper = await createMultiBlockWrapper([
                {
                    rootClass: 'root-x',
                    blockName: 'shim_combo_two_calls_block_x',
                    defaultContent: '<div class="default-x"></div>',
                },
                {
                    rootClass: 'root-y',
                    blockName: 'shim_combo_two_calls_block_y',
                    defaultContent: '<div class="default-y"></div>',
                },
            ]);

            expect(wrapper.find('.root-x .override-x').exists()).toBeTruthy();
            expect(wrapper.find('.root-x .default-x').exists()).toBeFalsy();
            expect(wrapper.find('.root-y .override-y').exists()).toBeTruthy();
            expect(wrapper.find('.root-y .default-y').exists()).toBeFalsy();
        });

        it('correctly applies three blocks from one plugin template across three mounted sw-blocks', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_combo_three_blocks_a %}
                        {% parent %}
                        <div class="override-a"></div>
                    {% endblock %}
                    {% block shim_combo_three_blocks_b %}
                        {% parent %}
                        <div class="override-b"></div>
                    {% endblock %}
                    {% block shim_combo_three_blocks_c %}
                        <div class="override-c"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createMultiBlockWrapper([
                {
                    rootClass: 'root-a',
                    blockName: 'shim_combo_three_blocks_a',
                    defaultContent: '<div class="default-a"></div>',
                },
                {
                    rootClass: 'root-b',
                    blockName: 'shim_combo_three_blocks_b',
                    defaultContent: '<div class="default-b"></div>',
                },
                {
                    rootClass: 'root-c',
                    blockName: 'shim_combo_three_blocks_c',
                    defaultContent: '<div class="default-c"></div>',
                },
            ]);

            // block-a and block-b: parent kept, override appended
            expect(wrapper.find('.root-a .default-a + .override-a').exists()).toBeTruthy();
            expect(wrapper.find('.root-b .default-b + .override-b').exists()).toBeTruthy();

            // block-c: parent replaced (no {% parent %})
            expect(wrapper.find('.root-c .default-c').exists()).toBeFalsy();
            expect(wrapper.find('.root-c .override-c').exists()).toBeTruthy();
        });
    });

    // ─── Interoperability with native sw-block extends ────────────────────────

    describe('interoperability with native sw-block extends', () => {
        it('stacks a native <sw-block extends> on top of an already-registered Twig shim override', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_native_on_shim %}
                        {% parent %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_native_on_shim',
                nativeExtensions: `
                    <sw-block extends="shim_interop_native_on_shim">
                        <sw-block-parent />
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            expect(wrapper.find('.default-content').exists()).toBeTruthy();
            expect(wrapper.find('.shim-content').exists()).toBeTruthy();
            expect(wrapper.find('.native-content').exists()).toBeTruthy();
        });

        it('renders content in the correct DOM order: default, then shim, then native', async () => {
            // The shim is always registered before mount (boot time), so it is
            // added to the block context first. The native extension mounts later
            // and is stacked on top of the shim.
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_shim_below_native %}
                        {% parent %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_shim_below_native',
                nativeExtensions: `
                    <sw-block extends="shim_interop_shim_below_native">
                        <sw-block-parent />
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            // default → shim → native (all chained via parent)
            expect(wrapper.find('.default-content + .shim-content + .native-content').exists()).toBeTruthy();
        });

        it('renders only native content when the native override has no <sw-block-parent />', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_native_no_parent %}
                        {% parent %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_native_no_parent',
                nativeExtensions: `
                    <sw-block extends="shim_interop_native_no_parent">
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            expect(wrapper.find('.shim-content').exists()).toBeFalsy();
            expect(wrapper.find('.default-content').exists()).toBeFalsy();
            expect(wrapper.find('.native-content').exists()).toBeTruthy();
        });

        it('chains a Twig-only shim (no {% parent %}) with a native <sw-block extends> that uses <sw-block-parent />', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_interop_twig_base_native_ext %}
                        <div class="shim-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_interop_twig_base_native_ext',
                nativeExtensions: `
                    <sw-block extends="shim_interop_twig_base_native_ext">
                        <sw-block-parent />
                        <div class="native-content"></div>
                    </sw-block>
                `,
            });

            expect(wrapper.find('.default-content').exists()).toBeFalsy();
            expect(wrapper.find('.shim-content').exists()).toBeTruthy();
            expect(wrapper.find('.native-content').exists()).toBeTruthy();
            expect(wrapper.find('.shim-content + .native-content').exists()).toBeTruthy();
        });
    });

    // ─── Reactivity and data scope ───────────────────────────────────────────

    describe('reactivity and data scope', () => {
        it('renders reactive component data accessed via {{ }} interpolation inside the Twig override', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_interpolation %}
                        <div class="data-output">{{ productName }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_interpolation',
                extraData: { productName: 'My Product' },
            });

            expect(wrapper.find('.data-output').text()).toBe('My Product');
        });

        it('updates the rendered output when reactive component data used in the Twig override changes', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_update %}
                        <div class="data-output">{{ productName }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_update',
                extraData: { productName: 'Initial Name' },
            });

            expect(wrapper.find('.data-output').text()).toBe('Initial Name');

            await wrapper.setData({ productName: 'Updated Name' });

            expect(wrapper.find('.data-output').text()).toBe('Updated Name');
        });

        it('evaluates a v-if directive inside the shimmed override using the host component data scope', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_vif_initial_true %}
                        <div v-if="isVisible" class="visible-content">visible</div>
                    {% endblock %}
                `,
            });

            const wrapperTrue = await createWrapper({
                blockName: 'shim_vif_initial_true',
                extraData: { isVisible: true },
            });

            expect(wrapperTrue.find('.visible-content').exists()).toBeTruthy();
        });

        it('toggles v-if content reactively when the referenced data property on the host component changes', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_vif_toggle %}
                        <div v-if="isVisible" class="visible-content">visible</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_vif_toggle',
                extraData: { isVisible: true },
            });

            expect(wrapper.find('.visible-content').exists()).toBeTruthy();

            await wrapper.setData({ isVisible: false });

            expect(wrapper.find('.visible-content').exists()).toBeFalsy();
        });

        it('passes computed properties from the host component into the Twig override template', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_computed %}
                        <div class="computed-output">{{ fullName }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_computed',
                extraOptions: {
                    computed: {
                        fullName() {
                            return 'John Doe';
                        },
                    },
                },
            });

            expect(wrapper.find('.computed-output').text()).toBe('John Doe');
        });

        it('passes method references from the host component into the Twig override template', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_reactive_methods %}
                        <div class="method-output">{{ greet('World') }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_reactive_methods',
                extraOptions: {
                    methods: {
                        greet(name: string) {
                            return `Hello ${name}`;
                        },
                    },
                },
            });

            expect(wrapper.find('.method-output').text()).toBe('Hello World');
        });
    });

    // ─── Deprecation warnings ────────────────────────────────────────────────

    describe('deprecation warnings', () => {
        it('emits a console.warn deprecation message when the first sw-block with a shim entry mounts', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_emits %}<div></div>{% endblock %}`,
            });

            await createWrapper({ blockName: 'shim_warn_emits' });

            expect(consoleSpy).toHaveBeenCalled();
        });

        it('includes the block name in the deprecation warning message', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_block_name %}<div></div>{% endblock %}`,
            });

            await createWrapper({ blockName: 'shim_warn_block_name' });

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('shim_warn_block_name'));
        });

        it('includes the native migration hint in the deprecation warning message', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_migration_hint %}<div></div>{% endblock %}`,
            });

            await createWrapper({ blockName: 'shim_warn_migration_hint' });

            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('<sw-block extends='));
        });

        it('emits the deprecation warning only once per block name across multiple mount/unmount cycles', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_only_once %}<div></div>{% endblock %}`,
            });

            const wrapper = await createWrapper({ blockName: 'shim_warn_only_once' });

            expect(consoleSpy).toHaveBeenCalledTimes(1);

            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });
            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });

            expect(consoleSpy).toHaveBeenCalledTimes(1);
        });

        it('emits separate deprecation warnings for each distinct block name that has a shim override', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_warn_separate_a %}<div></div>{% endblock %}
                    {% block shim_warn_separate_b %}<div></div>{% endblock %}
                `,
            });

            const swBlock = await wrapTestComponent('sw-block', { sync: true });
            const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });
            mount(
                {
                    template: `
                        <div>
                            <sw-block name="shim_warn_separate_a" :data="$dataScope()"></sw-block>
                            <sw-block name="shim_warn_separate_b" :data="$dataScope()"></sw-block>
                        </div>
                    `,
                },
                {
                    global: {
                        mocks: { $dataScope: getBlockDataScope },
                        components: {
                            'sw-block': swBlock,
                            'sw-block-parent': swBlockParent,
                        },
                    },
                },
            );

            expect(consoleSpy).toHaveBeenCalledTimes(2);
            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('shim_warn_separate_a'));
            expect(consoleSpy).toHaveBeenCalledWith(expect.stringContaining('shim_warn_separate_b'));
        });

        it('does not emit a deprecation warning when no Twig override targets the mounted sw-block name', async () => {
            await createWrapper({ blockName: 'shim_warn_no_override' });

            expect(consoleSpy).not.toHaveBeenCalled();
        });
    });

    // ─── Lifecycle and cleanup ───────────────────────────────────────────────

    describe('lifecycle and cleanup', () => {
        it('removes shim slots from the block context when the host sw-block component unmounts', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_lifecycle_remove %}<div class="override-content"></div>{% endblock %}`,
            });

            const wrapper = await createWrapper({ blockName: 'shim_lifecycle_remove' });

            expect(wrapper.find('.override-content').exists()).toBeTruthy();

            await wrapper.setData({ renderHost: false });

            expect(wrapper.find('.override-content').exists()).toBeFalsy();
        });

        it('re-adds shim slots when the host sw-block component remounts after having been unmounted', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_lifecycle_remount %}<div class="override-content"></div>{% endblock %}`,
            });

            const wrapper = await createWrapper({ blockName: 'shim_lifecycle_remount' });

            await wrapper.setData({ renderHost: false });

            expect(wrapper.find('.override-content').exists()).toBeFalsy();

            await wrapper.setData({ renderHost: true });

            expect(wrapper.find('.override-content').exists()).toBeTruthy();
        });

        it('does not accumulate duplicate shim slot entries across repeated mount/unmount/remount cycles', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_lifecycle_no_duplicates %}
                        {% parent %}
                        <div class="override-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_lifecycle_no_duplicates' });

            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });
            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });

            expect(wrapper.findAll('.override-content')).toHaveLength(1);
        });

        it('correctly re-applies {% parent %} resolution after an unmount/remount cycle', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_lifecycle_parent_remount %}
                        {% parent %}
                        <div class="override-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_lifecycle_parent_remount' });

            expect(wrapper.find('.default-content + .override-content').exists()).toBeTruthy();

            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });

            expect(wrapper.find('.default-content + .override-content').exists()).toBeTruthy();
        });

        it('renders {% parent %} content correctly after many reactive updates and a host sw-block remount', async () => {
            // Regression guard for the providedParents accumulation bug. The old
            // push() implementation added one entry per computed re-run without a
            // matching pop, growing the array unboundedly. After a host remount a
            // fresh sw-block-parent instance would pop from that array; this test
            // verifies the resulting DOM is still structurally correct.
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_parent_after_reactive_remount %}
                        {% parent %}
                        <div class="override-content"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_parent_after_reactive_remount',
                defaultContent: '<div class="default-content">{{ label }}</div>',
                extraData: { label: 'initial' },
            });

            await wrapper.setData({ label: 'a' });
            await wrapper.setData({ label: 'ab' });
            await wrapper.setData({ label: 'abc' });

            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });

            expect(wrapper.findAll('.default-content')).toHaveLength(1);
            expect(wrapper.findAll('.override-content')).toHaveLength(1);
            expect(wrapper.find('.default-content + .override-content').exists()).toBeTruthy();
        });
    });

    // ─── Component instance stability (focus preservation) ───────────────────
    //
    // ShimContent (the internal Vue component that renders the shim template)
    // must be reused across reactive updates, not destroyed and recreated.
    // Remounting replaces the DOM node, which would strip focus from any active
    // input element inside the shimmed content on every keystroke.

    describe('component instance stability', () => {
        it('preserves the DOM element identity of shimmed content across reactive data updates', async () => {
            // Vue reuses a component instance when its VNode type is the same object
            // reference between renders. If the type changes, Vue unmounts the old
            // instance and mounts a new one, which replaces the DOM node and strips
            // focus from any active input inside the shimmed content.
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_stable_dom_node %}
                        <div class="override-content">{{ label }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_stable_dom_node',
                extraData: { label: 'initial' },
            });

            const domNodeBefore = wrapper.find('.override-content').element;

            await wrapper.setData({ label: 'a' });
            await wrapper.setData({ label: 'ab' });
            await wrapper.setData({ label: 'abc' });

            // A new element reference here would mean ShimContent was remounted
            // (focus lost); the same reference means it was updated in-place (focus kept).
            expect(wrapper.find('.override-content').element).toBe(domNodeBefore);
        });

        it('reflects the latest reactive data in the shimmed content after multiple updates', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_stable_data_updates %}
                        <div class="override-content">{{ label }}</div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_stable_data_updates',
                extraData: { label: 'initial' },
            });

            await wrapper.setData({ label: 'a' });
            await wrapper.setData({ label: 'ab' });
            await wrapper.setData({ label: 'abc' });

            expect(wrapper.find('.override-content').text()).toBe('abc');
        });
    });

    // ─── Nested {% block %} inside Twig override templates ───────────────────

    describe('nested {% block %} inside Twig override templates', () => {
        it('renders the inner content of a nested {% block %} verbatim as Vue template HTML', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_nested_single %}
                        {% block shim_nested_single_inner %}
                            <div class="inner-content"></div>
                        {% endblock %}
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_nested_single' });

            expect(wrapper.find('.inner-content').exists()).toBeTruthy();
        });

        it('handles two levels of nested {% block %} nesting and renders the innermost content correctly', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_nested_deep_l1 %}
                        <div class="level-1">
                            {% block shim_nested_deep_l2 %}
                                <div class="level-2">
                                    {% block shim_nested_deep_l3 %}
                                        <div class="level-3"></div>
                                    {% endblock %}
                                </div>
                            {% endblock %}
                        </div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_nested_deep_l1' });

            expect(wrapper.find('.level-1').exists()).toBeTruthy();
            expect(wrapper.find('.level-1 .level-2').exists()).toBeTruthy();
            expect(wrapper.find('.level-1 .level-2 .level-3').exists()).toBeTruthy();
        });

        it('renders outer-block {% parent %} before nested block content', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_nested_with_parent %}
                        {% parent %}
                        {% block shim_nested_with_parent_inner %}
                            <div class="inner-content"></div>
                        {% endblock %}
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_nested_with_parent' });

            expect(wrapper.find('.default-content').exists()).toBeTruthy();
            expect(wrapper.find('.inner-content').exists()).toBeTruthy();
            expect(wrapper.find('.default-content + .inner-content').exists()).toBeTruthy();
        });

        it('renders the default content innermost when {% parent %} is wrapped by outer HTML', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_parent_innermost %}
                        <div class="outer">
                            <div class="inner">
                                {% parent %}
                            </div>
                        </div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({ blockName: 'shim_parent_innermost' });

            expect(wrapper.find('.outer > .inner > .default-content').exists()).toBeTruthy();
        });
    });

    // ─── HTML attributes and Vue directives pass-through ─────────────────────

    describe('HTML attributes and Vue directives inside Twig override templates', () => {
        it('passes v-bind (: shorthand) attribute bindings through verbatim to the rendered output', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_directive_vbind %}
                        <div :class="dynamicClass" class="bound-element"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_directive_vbind',
                extraData: { dynamicClass: 'extra-class' },
            });

            expect(wrapper.find('.bound-element.extra-class').exists()).toBeTruthy();
        });

        it('passes @click event handler attributes through verbatim and invokes the host component method', async () => {
            const clickHandler = jest.fn();

            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_directive_click %}
                        <button @click="handleClick" class="clickable-button">click</button>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_directive_click',
                extraOptions: {
                    methods: { handleClick: clickHandler },
                },
            });

            await wrapper.find('.clickable-button').trigger('click');

            expect(clickHandler).toHaveBeenCalledTimes(1);
        });

        it('passes :class object bindings through verbatim and reflects them reactively in the DOM', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_directive_class_binding %}
                        <div :class="{ active: isActive, disabled: !isActive }" class="element"></div>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_directive_class_binding',
                extraData: { isActive: true },
            });

            expect(wrapper.find('.element.active').exists()).toBeTruthy();
            expect(wrapper.find('.element.disabled').exists()).toBeFalsy();

            await wrapper.setData({ isActive: false });

            expect(wrapper.find('.element.active').exists()).toBeFalsy();
            expect(wrapper.find('.element.disabled').exists()).toBeTruthy();
        });

        it('passes v-for directives through verbatim and renders the expected list items', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_directive_vfor %}
                        <ul>
                            <li v-for="item in items" :key="item" class="list-item">{{ item }}</li>
                        </ul>
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_directive_vfor',
                extraData: {
                    items: [
                        'alpha',
                        'beta',
                        'gamma',
                    ],
                },
            });

            const listItems = wrapper.findAll('.list-item');
            expect(listItems).toHaveLength(3);
            expect(listItems[0].text()).toBe('alpha');
            expect(listItems[1].text()).toBe('beta');
            expect(listItems[2].text()).toBe('gamma');
        });
    });

    // ─── Global Vue component references ─────────────────────────────────────

    describe('Vue component references inside Twig override templates', () => {
        it('resolves a globally registered Vue component referenced by tag name inside a Twig block override', async () => {
            const GlobalTestComponent = {
                name: 'global-test-component',
                template: '<div class="global-component-rendered"></div>',
            };

            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_global_component_ref %}
                        <global-test-component />
                    {% endblock %}
                `,
            });

            const swBlock = await wrapTestComponent('sw-block', { sync: true });
            const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });
            const wrapper = mount(
                {
                    template: `
                        <div class="component-root">
                            <sw-block name="shim_global_component_ref" :data="$dataScope()"></sw-block>
                        </div>
                    `,
                },
                {
                    global: {
                        mocks: { $dataScope: getBlockDataScope },
                        components: {
                            'sw-block': swBlock,
                            'sw-block-parent': swBlockParent,
                            'global-test-component': GlobalTestComponent,
                        },
                    },
                },
            );

            expect(wrapper.find('.global-component-rendered').exists()).toBeTruthy();
        });
    });

    // ─── Known limitations ───────────────────────────────────────────────────

    describe('known limitations (unsupported Twig control flow)', () => {
        it('produces empty output for a {% if %} Twig control flow tag inside an override block', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_limitation_twig_if %}
                        {% if someCondition %}
                            <div class="twig-if-content"></div>
                        {% endif %}
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_limitation_twig_if',
                defaultContent: '',
                extraData: { someCondition: true },
            });

            expect(wrapper.find('.twig-if-content').exists()).toBeFalsy();
        });

        it('produces empty output for a {% for %} Twig control flow tag inside an override block', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_limitation_twig_for %}
                        {% for item in items %}
                            <div class="twig-for-item"></div>
                        {% endfor %}
                    {% endblock %}
                `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_limitation_twig_for',
                defaultContent: '',
                extraData: {
                    items: [
                        'a',
                        'b',
                        'c',
                    ],
                },
            });

            expect(wrapper.find('.twig-for-item').exists()).toBeFalsy();
        });
    });

    // ─── Edge cases ──────────────────────────────────────────────────────────

    describe('edge cases', () => {
        it('silently ignores a malformed Twig template in Shopware.Component.override without crashing', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_edge_malformed %} <div {{ unclosed-attr `,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_edge_malformed',
                defaultContent: '<div class="default-content"></div>',
            });

            // Default content renders unchanged since the shim could not index any
            // blocks from the unparseable template
            expect(wrapper.find('.default-content').exists()).toBeTruthy();
        });

        it('handles an override template with a whitespace-only block body without crashing', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_edge_whitespace_only %}   {% endblock %}`,
            });

            const wrapper = await createWrapper({
                blockName: 'shim_edge_whitespace_only',
                defaultContent: '',
            });

            expect(wrapper.find('.component-root').exists()).toBeTruthy();
        });

        it('handles multiple top-level {% block %} definitions in a single override call', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_edge_multi_top_a %}<div class="override-a"></div>{% endblock %}
                    {% block shim_edge_multi_top_b %}<div class="override-b"></div>{% endblock %}
                `,
            });

            const swBlock = await wrapTestComponent('sw-block', { sync: true });
            const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });
            const wrapper = mount(
                {
                    template: `
                        <div>
                            <div class="root-a">
                                <sw-block name="shim_edge_multi_top_a" :data="$dataScope()">
                                    <div class="default-a"></div>
                                </sw-block>
                            </div>
                            <div class="root-b">
                                <sw-block name="shim_edge_multi_top_b" :data="$dataScope()">
                                    <div class="default-b"></div>
                                </sw-block>
                            </div>
                        </div>
                    `,
                },
                {
                    global: {
                        mocks: { $dataScope: getBlockDataScope },
                        components: {
                            'sw-block': swBlock,
                            'sw-block-parent': swBlockParent,
                        },
                    },
                },
            );

            expect(wrapper.find('.root-a .override-a').exists()).toBeTruthy();
            expect(wrapper.find('.root-a .default-a').exists()).toBeFalsy();
            expect(wrapper.find('.root-b .override-b').exists()).toBeTruthy();
            expect(wrapper.find('.root-b .default-b').exists()).toBeFalsy();
        });
    });

    // ─── Multiple simultaneous instances of the same block name ─────────────
    //
    // When two <sw-block name="foo"> elements are mounted at the same time (e.g.
    // in a list where the same block name appears per-row), each instance must
    // render its own shim override exactly once. Before the M-8 fix, shim slots
    // were registered in the shared global blockContext by each instance, causing
    // every instance to see and double-render all siblings' shim slots.

    describe('multiple simultaneous instances of the same block name', () => {
        it('renders the shim override once per instance when two same-name sw-blocks mount simultaneously', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_multi_instance_isolation %}
                        <div class="override-content"></div>
                    {% endblock %}
                `,
            });

            const swBlock = await wrapTestComponent('sw-block', { sync: true });
            const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });
            const wrapper = mount(
                {
                    template: `
                        <div>
                            <div class="instance-a">
                                <sw-block name="shim_multi_instance_isolation" :data="$dataScope()">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
                            <div class="instance-b">
                                <sw-block name="shim_multi_instance_isolation" :data="$dataScope()">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
                        </div>
                    `,
                },
                {
                    global: {
                        mocks: { $dataScope: getBlockDataScope },
                        components: {
                            'sw-block': swBlock,
                            'sw-block-parent': swBlockParent,
                        },
                    },
                },
            );

            // Each instance should show the override exactly once — not duplicated
            expect(wrapper.findAll('.instance-a .override-content')).toHaveLength(1);
            expect(wrapper.findAll('.instance-b .override-content')).toHaveLength(1);
            // The default content should be replaced in both instances
            expect(wrapper.find('.instance-a .default-content').exists()).toBeFalsy();
            expect(wrapper.find('.instance-b .default-content').exists()).toBeFalsy();
        });

        it('stacks {% parent %} correctly per instance when two same-name sw-blocks mount simultaneously', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `
                    {% block shim_multi_instance_parent_isolation %}
                        {% parent %}
                        <div class="override-parent-content"></div>
                    {% endblock %}
                `,
            });

            const swBlock = await wrapTestComponent('sw-block', { sync: true });
            const swBlockParent = await wrapTestComponent('sw-block-parent', { sync: true });
            const wrapper = mount(
                {
                    template: `
                        <div>
                            <div class="instance-a">
                                <sw-block name="shim_multi_instance_parent_isolation" :data="$dataScope()">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
                            <div class="instance-b">
                                <sw-block name="shim_multi_instance_parent_isolation" :data="$dataScope()">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
                        </div>
                    `,
                },
                {
                    global: {
                        mocks: { $dataScope: getBlockDataScope },
                        components: {
                            'sw-block': swBlock,
                            'sw-block-parent': swBlockParent,
                        },
                    },
                },
            );

            // Each instance: default → override, each exactly once
            expect(wrapper.findAll('.instance-a .default-content')).toHaveLength(1);
            expect(wrapper.findAll('.instance-a .override-parent-content')).toHaveLength(1);
            expect(wrapper.find('.instance-a .default-content + .override-parent-content').exists()).toBeTruthy();

            expect(wrapper.findAll('.instance-b .default-content')).toHaveLength(1);
            expect(wrapper.findAll('.instance-b .override-parent-content')).toHaveLength(1);
            expect(wrapper.find('.instance-b .default-content + .override-parent-content').exists()).toBeTruthy();
        });
    });
});
