/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import createDataScopeFixture from '../../sw-block-override.spec/test-utils/create-data-scope-fixture';
import { setupShimSpec, createWrapper } from './test-utils';

describe('Twig → Native Block Runtime Adapter (shim): basic rendering and {% parent %}', () => {
    setupShimSpec();

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
                                <sw-block name="shim_multi_diff_block_a" :data="$dataScope">
                                    <div class="default-a"></div>
                                </sw-block>
                            </div>
                            <div class="root-b">
                                <sw-block name="shim_multi_diff_block_b" :data="$dataScope">
                                    <div class="default-b"></div>
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
});
