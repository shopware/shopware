/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import ComponentFactory from 'src/core/factory/async-component.factory';
import { registerNativeExtensionTargets } from 'src/core/factory/native-extension-targets';
import {
    expectOnlyBranch,
    mountNativeBlockComponent,
    settleLegacyChain,
    setupComponentFactoryHooks,
} from './native-block-condition.fixtures';

async function mountWithOverride(componentName) {
    const swBlock = (await import('src/app/component/structure/sw-block-override/sw-block/index')).default;
    const swBlockParent = (await import('src/app/component/structure/sw-block-override/sw-block-parent/index')).default;
    const swCard = {
        template: '<div class="card"><header><slot name="header">fallback</slot></header><main><slot /></main></div>',
    };
    const built = await ComponentFactory.build(componentName);

    return mount(
        {
            components: { 'inner-comp': built },
            template: `
                <div>
                    <inner-comp />
                    <sw-block extends="nep_block"><sw-block-parent /><em>from override</em></sw-block>
                </div>
            `,
        },
        { global: { components: { 'sw-card': swCard, 'sw-block': swBlock, 'sw-block-parent': swBlockParent } } },
    );
}

describe('core/factory/async-component.factory.ts - native extension points in Twig components', () => {
    setupComponentFactoryHooks();

    it('renders a native override inside a plain target block', async () => {
        registerNativeExtensionTargets({ component: 'nep-plain', blocks: ['nep_block'] });
        ComponentFactory.register('nep-plain', {
            template: '<div class="wrap">{% block nep_block %}<p>original</p>{% endblock %}</div>',
        });

        const wrapper = await mountWithOverride('nep-plain');

        expect(wrapper.find('.wrap').html().replace(/\s+/g, '')).toBe(
            '<divclass="wrap"><p>original</p><em>fromoverride</em></div>',
        );
    });

    it('renders a native override inside a slot template block', async () => {
        registerNativeExtensionTargets({ component: 'nep-slot', blocks: ['nep_block'] });
        ComponentFactory.register('nep-slot', {
            template: '<sw-card>{% block nep_block %}<template #header><p>original</p></template>{% endblock %}</sw-card>',
        });

        const wrapper = await mountWithOverride('nep-slot');

        // The override has to land in the card's header slot, not in its default slot.
        expect(wrapper.find('header').html().replace(/\s+/g, '')).toBe(
            '<header><p>original</p><em>fromoverride</em></header>',
        );
    });

    describe('v-if chains split by an extension point', () => {
        const branches = [
            '.on',
            '.off',
        ];

        async function mountChain(componentName, blocks) {
            registerNativeExtensionTargets({ component: componentName, blocks });
            ComponentFactory.register(componentName, {
                template:
                    '<div class="wrap">' +
                    '{% block nep_chain_if %}<p class="on" v-if="active">on</p>{% endblock %}' +
                    '{% block nep_chain_else %}<p class="off" v-else>off</p>{% endblock %}' +
                    '</div>',
                data() {
                    return { active: true };
                },
            });

            return mountNativeBlockComponent(componentName);
        }

        it.each([
            [
                'the v-if block',
                'nep-chain-if',
                ['nep_chain_if'],
            ],
            [
                'the v-else block',
                'nep-chain-else',
                ['nep_chain_else'],
            ],
        ])('renders exactly one branch and follows the condition when %s is the target', async (_label, name, blocks) => {
            const wrapper = await mountChain(name, blocks);

            expectOnlyBranch(wrapper, branches, '.on');

            await wrapper.setData({ active: false });
            await settleLegacyChain(wrapper);

            expectOnlyBranch(wrapper, branches, '.off');

            await wrapper.setData({ active: true });
            await settleLegacyChain(wrapper);

            expectOnlyBranch(wrapper, branches, '.on');

            wrapper.unmount();
        });
    });
});
