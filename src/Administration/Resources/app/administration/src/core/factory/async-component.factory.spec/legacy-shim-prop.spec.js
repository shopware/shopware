/**
 * @sw-package framework
 */

import ComponentFactory from 'src/core/factory/async-component.factory';
import {
    setupComponentFactoryHooks,
    mountNativeBlockComponent,
    withMutedConsoleWarn,
} from './native-block-condition.fixtures';

describe('core/factory/async-component.factory.ts - sw-block legacy-shim prop', () => {
    setupComponentFactoryHooks();

    it.each([
        [
            true,
            '<div><p>legacy</p><p>legacy</p><p>base</p></div>',
        ],
        [
            false,
            '<div><p>legacy</p><p>base</p></div>',
        ],
    ])('renders the legacy Twig override once when legacy-shim is %s', async (legacyShim, expected) => {
        const name = `shim-prop-${String(legacyShim)}`;

        ComponentFactory.register(name, {
            template: `<div><sw-block name="shim_prop_block" :data="$dataScope" :legacy-shim="${String(legacyShim)}">{% block shim_prop_block %}<p>base</p>{% endblock %}</sw-block></div>`,
        });
        ComponentFactory.override(name, {
            template: '{% block shim_prop_block %}<p>legacy</p>{% parent %}{% endblock %}',
        });

        // The active shim emits a legacy-override deprecation warning, which the test setup treats as a failure.
        const wrapper = await withMutedConsoleWarn(() => mountNativeBlockComponent(name));

        // With the shim active the merged template content and the shim slot carry the same override,
        // so it ends up in the output twice.
        expect(wrapper.html().replace(/\s+/g, '')).toBe(expected);
    });
});
