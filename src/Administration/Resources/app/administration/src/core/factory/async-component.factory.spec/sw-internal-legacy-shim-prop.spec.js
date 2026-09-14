/**
 * @sw-package framework
 */

import ComponentFactory from 'src/core/factory/async-component.factory';
import { setupComponentFactoryHooks, mountNativeBlockComponent } from './native-block-condition.fixtures';

describe('core/factory/async-component.factory.ts - sw-block sw-internal-legacy-shim prop', () => {
    setupComponentFactoryHooks();

    it('renders a merged legacy override once when the shim is off, as the generated wrapper sets it', async () => {
        const name = 'shim-prop-off';

        ComponentFactory.register(name, {
            template: `<div><sw-block name="shim_prop_block" :data="$dataScope" :sw-internal-legacy-shim="false">{% block shim_prop_block %}<p>base</p>{% endblock %}</sw-block></div>`,
        });
        ComponentFactory.override(name, {
            template: '{% block shim_prop_block %}<p>legacy</p>{% parent %}{% endblock %}',
        });

        const wrapper = await mountNativeBlockComponent(name);

        expect(wrapper.html().replace(/\s+/g, '')).toBe('<div><p>legacy</p><p>base</p></div>');
    });
});
