/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import createDataScopeFixture from '../../sw-block-override.spec/test-utils/create-data-scope-fixture';
import { setupShimSpec, createWrapper } from './test-utils';

describe('Twig → Native Block Runtime Adapter (shim): lifecycle and simultaneous instances', () => {
    setupShimSpec();

    describe('lifecycle and cleanup', () => {
        it('removes the Twig override when the host sw-block component unmounts', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_lifecycle_remove %}<div class="override-content"></div>{% endblock %}`,
            });

            const wrapper = await createWrapper({ blockName: 'shim_lifecycle_remove' });

            expect(wrapper.find('.override-content').exists()).toBeTruthy();

            await wrapper.setData({ renderHost: false });

            expect(wrapper.find('.override-content').exists()).toBeFalsy();
        });

        it('re-renders the Twig override when the host sw-block component remounts after having been unmounted', async () => {
            Shopware.Component.override('sw-product-detail', {
                template: `{% block shim_lifecycle_remount %}<div class="override-content"></div>{% endblock %}`,
            });

            const wrapper = await createWrapper({ blockName: 'shim_lifecycle_remount' });

            await wrapper.setData({ renderHost: false });

            expect(wrapper.find('.override-content').exists()).toBeFalsy();

            await wrapper.setData({ renderHost: true });

            expect(wrapper.find('.override-content').exists()).toBeTruthy();
        });

        it('does not render the Twig override twice across repeated mount/unmount/remount cycles', async () => {
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
                                <sw-block name="shim_multi_instance_isolation" :data="$dataScope">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
                            <div class="instance-b">
                                <sw-block name="shim_multi_instance_isolation" :data="$dataScope">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
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
                                <sw-block name="shim_multi_instance_parent_isolation" :data="$dataScope">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
                            <div class="instance-b">
                                <sw-block name="shim_multi_instance_parent_isolation" :data="$dataScope">
                                    <div class="default-content"></div>
                                </sw-block>
                            </div>
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
