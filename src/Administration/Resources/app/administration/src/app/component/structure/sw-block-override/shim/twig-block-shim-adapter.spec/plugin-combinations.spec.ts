/**
 * @sw-package framework
 * @group disabledCompat
 */
import { setupShimSpec, createWrapper, createMultiBlockWrapper } from './test-utils';

describe('Twig → Native Block Runtime Adapter (shim): multiple plugins and blocks', () => {
    setupShimSpec();

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
});
