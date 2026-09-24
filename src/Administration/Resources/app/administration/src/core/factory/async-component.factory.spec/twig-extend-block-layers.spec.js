/**
 * @sw-package framework
 */

import {
    ComponentFactory,
    mountNativeBlockComponent,
    setupComponentFactoryHooks,
    withMutedConsoleWarn,
} from './native-block-condition.fixtures';

describe('core/factory/async-component.factory.ts - Twig blocks of extended components', () => {
    setupComponentFactoryHooks();

    beforeEach(() => {
        ComponentFactory.register('sw-extend-base', {
            template: `
                <div>
                    <sw-block name="extend_base_block">
                        <span class="base">base</span>
                    </sw-block>
                </div>
            `,
        });
        ComponentFactory.extend('sw-extend-child', 'sw-extend-base', {
            template: `
                {% block extend_base_block %}
                    {% parent %}
                    <b class="child">child</b>
                {% endblock %}
            `,
        });
    });

    it('renders the Twig block of an extending component on the native block it inherits', async () => {
        const wrapper = await withMutedConsoleWarn(() => mountNativeBlockComponent('sw-extend-child'));

        expect(wrapper.find('.base + .child').exists()).toBe(true);
    });

    it('does not apply the Twig block of an extending component to the component it extends', async () => {
        const wrapper = await withMutedConsoleWarn(() => mountNativeBlockComponent('sw-extend-base'));

        expect(wrapper.find('.base').exists()).toBe(true);
        expect(wrapper.find('.child').exists()).toBe(false);
    });

    it('stacks the Twig block of an extending component on top of the overrides of the component it extends', async () => {
        ComponentFactory.override('sw-extend-base', {
            template: `
                {% block extend_base_block %}
                    {% parent %}
                    <i class="base-override">override</i>
                {% endblock %}
            `,
        });

        const wrapper = await withMutedConsoleWarn(() => mountNativeBlockComponent('sw-extend-child'));

        expect(wrapper.find('.base + .base-override + .child').exists()).toBe(true);
    });
});
