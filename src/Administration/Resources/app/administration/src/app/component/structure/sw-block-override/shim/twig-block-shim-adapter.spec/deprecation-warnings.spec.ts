/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import createDataScopeFixture from '../../sw-block-override.spec/test-utils/create-data-scope-fixture';
import { setupShimSpec, createWrapper } from './test-utils';

describe('Twig → Native Block Runtime Adapter (shim): deprecation warnings', () => {
    const { consoleSpy } = setupShimSpec();

    describe('deprecation warnings', () => {
        it('emits a console.warn deprecation message when the first sw-block with a shim entry mounts', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_emits %}<div></div>{% endblock %}`,
            });

            await createWrapper({ blockName: 'shim_warn_emits' });

            expect(consoleSpy()).toHaveBeenCalled();
        });

        it('includes the block name in the deprecation warning message', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_block_name %}<div></div>{% endblock %}`,
            });

            await createWrapper({ blockName: 'shim_warn_block_name' });

            expect(consoleSpy()).toHaveBeenCalledWith(expect.stringContaining('shim_warn_block_name'));
        });

        it('includes the native migration hint in the deprecation warning message', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_migration_hint %}<div></div>{% endblock %}`,
            });

            await createWrapper({ blockName: 'shim_warn_migration_hint' });

            expect(consoleSpy()).toHaveBeenCalledWith(expect.stringContaining('<sw-block extends='));
        });

        it('emits the deprecation warning only once per block name across multiple mount/unmount cycles', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_warn_only_once %}<div></div>{% endblock %}`,
            });

            const wrapper = await createWrapper({ blockName: 'shim_warn_only_once' });

            expect(consoleSpy()).toHaveBeenCalledTimes(1);

            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });
            await wrapper.setData({ renderHost: false });
            await wrapper.setData({ renderHost: true });

            expect(consoleSpy()).toHaveBeenCalledTimes(1);
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
                            <sw-block name="shim_warn_separate_a" :data="$dataScope"></sw-block>
                            <sw-block name="shim_warn_separate_b" :data="$dataScope"></sw-block>
                        </div>
                    `,
                },
                {
                    global: {
                        plugins: [createDataScopeFixture()],
                        components: {
                            'sw-block': swBlock,
                            'sw-block-parent': swBlockParent,
                        },
                    },
                },
            );

            expect(consoleSpy()).toHaveBeenCalledTimes(2);
            expect(consoleSpy()).toHaveBeenCalledWith(expect.stringContaining('shim_warn_separate_a'));
            expect(consoleSpy()).toHaveBeenCalledWith(expect.stringContaining('shim_warn_separate_b'));
        });

        it('does not emit a deprecation warning when no Twig override targets the mounted sw-block name', async () => {
            await createWrapper({ blockName: 'shim_warn_no_override' });

            expect(consoleSpy()).not.toHaveBeenCalled();
        });
    });
});
