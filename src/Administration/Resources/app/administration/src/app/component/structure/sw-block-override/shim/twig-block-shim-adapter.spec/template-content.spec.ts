/**
 * @sw-package framework
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import createDataScopeFixture from '../../sw-block-override.spec/test-utils/create-data-scope-fixture';
import { setupShimSpec, createWrapper } from './test-utils';

describe('Twig → Native Block Runtime Adapter (shim): template content', () => {
    setupShimSpec();

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
                    items: ['alpha', 'beta', 'gamma'],
                },
            });

            const listItems = wrapper.findAll('.list-item');
            expect(listItems).toHaveLength(3);
            expect(listItems[0].text()).toBe('alpha');
            expect(listItems[1].text()).toBe('beta');
            expect(listItems[2].text()).toBe('gamma');
        });
    });

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
                            <sw-block name="shim_global_component_ref" :data="$dataScope"></sw-block>
                        </div>
                    `,
                },
                {
                    global: {
                        plugins: [createDataScopeFixture()],
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
                    items: ['a', 'b', 'c'],
                },
            });

            expect(wrapper.find('.twig-for-item').exists()).toBeFalsy();
        });
    });

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
                                <sw-block name="shim_edge_multi_top_a" :data="$dataScope">
                                    <div class="default-a"></div>
                                </sw-block>
                            </div>
                            <div class="root-b">
                                <sw-block name="shim_edge_multi_top_b" :data="$dataScope">
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
        });
    });
});
