/**
 * @sw-package framework
 */

import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import {
    ComponentFactory,
    expectOnlyBranch,
    mountNativeBlockComponent,
    setupComponentFactoryHooks,
    settleLegacyChain,
    withMutedConsoleWarn,
} from './native-block-condition.fixtures';

describe('core/factory/async-component.factory.ts - legacy Twig shim condition chains', () => {
    setupComponentFactoryHooks();

    it('does not override existing component methods with shim helpers', async () => {
        ComponentFactory.register('native-block-legacy-method-collision', {
            methods: {
                legacyIf() {
                    return true;
                },
            },
            template: '<div>{{ legacyIf() ? "kept" : "overridden" }}</div>',
        });

        const component = await ComponentFactory.build('native-block-legacy-method-collision');
        const wrapper = await mount(component, {});

        expect(wrapper.text()).toBe('kept');
    });

    it('renders a legacy Twig shim v-else case using the host component condition scope', async () => {
        ComponentFactory.register('native-block-legacy-twig-shim-else', {
            data() {
                return {
                    isConditionTrue: false,
                };
            },
            template: `
                <div>
                    <sw-block name="twig_shim_test_block" :data="$dataScope">
                        <div v-if="isConditionTrue" class="true-case">true</div>
                    </sw-block>
                </div>
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-shim-else', {
            template: `
                {% block twig_shim_test_block %}
                    {% parent %}
                    <div v-else class="false-case">false</div>
                {% endblock %}
            `,
        });

        const wrapper = await withMutedConsoleWarn(() => {
            return mountNativeBlockComponent('native-block-legacy-twig-shim-else');
        });

        expect(wrapper.find('.true-case').exists()).toBe(false);
        expect(wrapper.find('.false-case').exists()).toBe(true);

        wrapper.vm.isConditionTrue = true;
        await nextTick();

        expect(wrapper.find('.true-case').exists()).toBe(true);
        expect(wrapper.find('.false-case').exists()).toBe(false);
    });

    it('fails loudly when a legacy Twig conditional shim is rendered without host data scope', async () => {
        ComponentFactory.register('native-block-legacy-twig-shim-missing-data-scope', {
            data() {
                return {
                    condition1: false,
                };
            },
            template: `
                <div>
                    <sw-block name="twig_shim_block">
                        <div v-if="condition1" class="condition-one">one</div>
                    </sw-block>
                </div>
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-shim-missing-data-scope', {
            template: `
                {% block twig_shim_block %}
                    {% parent %}
                    <div v-else class="twig-fallback">fallback</div>
                {% endblock %}
            `,
        });

        await withMutedConsoleWarn(async () => {
            await expect(mountNativeBlockComponent('native-block-legacy-twig-shim-missing-data-scope')).rejects.toThrow(
                '[sw-block] Legacy Twig conditional override for block "twig_shim_block" ' +
                    'in component "native-block-legacy-twig-shim-missing-data-scope" requires host data scope. ' +
                    'Pass :data="$dataScope" to <sw-block name="twig_shim_block">.',
            );
        });
    });

    // eslint-disable-next-line jest/expect-expect
    it('renders legacy Twig shim condition chains across multiple template overrides', async () => {
        ComponentFactory.register('native-block-legacy-twig-shim-override-chain', {
            data() {
                return {
                    condition1: false,
                    condition2: false,
                };
            },
            template: `
                <div>
                    <sw-block name="chained_condition_block" :data="$dataScope">
                        <div v-if="condition1" class="condition-one">Condition 1</div>
                    </sw-block>
                </div>
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-shim-override-chain', {
            template: `
                {% block chained_condition_block %}
                    {% parent %}
                    <h1 v-else-if="condition2" class="condition-two">Override</h1>
                {% endblock %}
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-shim-override-chain', {
            template: `
                {% block chained_condition_block %}
                    {% parent %}
                    <h1 v-else class="fallback-condition">Override 2</h1>
                {% endblock %}
            `,
        });

        const wrapper = await withMutedConsoleWarn(() => {
            return mountNativeBlockComponent('native-block-legacy-twig-shim-override-chain');
        });
        const branches = [
            '.condition-one',
            '.condition-two',
            '.fallback-condition',
        ];

        expectOnlyBranch(wrapper, branches, '.fallback-condition');

        wrapper.vm.condition2 = true;
        await nextTick();
        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, '.condition-two');

        wrapper.vm.condition1 = true;
        await nextTick();
        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, '.condition-one');

        wrapper.vm.condition1 = false;
        wrapper.vm.condition2 = false;
        await nextTick();
        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, '.fallback-condition');
    });

    it('keeps a later native fallback behind a legacy Twig shim condition', async () => {
        ComponentFactory.register('native-block-legacy-twig-mixed-override-chain', {
            data() {
                return {
                    condition1: false,
                    condition2: true,
                };
            },
            template: `
                <div>
                    <sw-block name="mixed_chained_condition_block" :data="$dataScope">
                        <div v-if="condition1" class="condition-one">Condition 1</div>
                    </sw-block>

                    <sw-block extends="mixed_chained_condition_block">
                        <sw-block-parent />
                        <h1 v-else class="native-fallback-condition">Native fallback</h1>
                    </sw-block>
                </div>
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-mixed-override-chain', {
            template: `
                {% block mixed_chained_condition_block %}
                    {% parent %}
                    <h1 v-else-if="condition2" class="condition-two">Override</h1>
                {% endblock %}
            `,
        });

        const wrapper = await withMutedConsoleWarn(() => {
            return mountNativeBlockComponent('native-block-legacy-twig-mixed-override-chain');
        });

        await settleLegacyChain(wrapper);

        expect(wrapper.find('.condition-one').exists()).toBe(false);
        expect(wrapper.find('.condition-two').exists()).toBe(true);
        expect(wrapper.find('.native-fallback-condition').exists()).toBe(false);

        wrapper.vm.condition2 = false;
        await nextTick();
        await settleLegacyChain(wrapper);

        expect(wrapper.find('.condition-one').exists()).toBe(false);
        expect(wrapper.find('.condition-two').exists()).toBe(false);
        // Persistent shim chains schedule the later native branch after the removed shim branch has updated.
        void wrapper.html();
        void (await import('src/app/component/structure/sw-block-override/shim/legacy-condition-context')).default()
            .legacyConditionContext;
        await wrapper.vm.$nextTick();
        expect(wrapper.find('.native-fallback-condition').exists()).toBe(true);
    });

    // eslint-disable-next-line jest/expect-expect
    it('continues adjacent named block condition chains for legacy Twig shim v-else-if cases', async () => {
        ComponentFactory.register('native-block-legacy-twig-adjacent-named-chain', {
            data() {
                return {
                    condition1: false,
                    condition2: false,
                    conditionFromPlugin: true,
                };
            },
            template: `
                <div>
                    <sw-block name="adjacent_condition_block_one" :data="$dataScope">
                        <div v-if="condition1" class="native-one">one</div>
                    </sw-block>

                    <sw-block name="adjacent_condition_block_two" :data="$dataScope">
                        <div v-else-if="condition2" class="native-two">two</div>
                    </sw-block>
                </div>
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-adjacent-named-chain', {
            template: `
                {% block adjacent_condition_block_two %}
                    <div v-else-if="conditionFromPlugin" class="plugin-two">plugin two</div>
                {% endblock %}
            `,
        });

        const wrapper = await withMutedConsoleWarn(() => {
            return mountNativeBlockComponent('native-block-legacy-twig-adjacent-named-chain');
        });
        const branches = [
            '.native-one',
            '.native-two',
            '.plugin-two',
        ];

        await settleLegacyChain(wrapper);
        expectOnlyBranch(wrapper, branches, '.plugin-two');

        wrapper.vm.condition1 = true;
        await nextTick();
        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, '.native-one');

        wrapper.vm.condition1 = false;
        wrapper.vm.condition2 = true;
        await nextTick();
        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, null);

        wrapper.vm.condition2 = false;
        wrapper.vm.conditionFromPlugin = false;
        await nextTick();
        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, null);
    });

    // eslint-disable-next-line jest/expect-expect
    it('renders a later legacy Twig fallback after an earlier legacy Twig v-if misses', async () => {
        ComponentFactory.register('native-block-legacy-twig-started-chain', {
            data() {
                return {
                    conditionFromPluginOne: false,
                };
            },
            template: `
                <div>
                    <sw-block name="twig_started_condition_block" :data="$dataScope">
                        <div class="default-content">Default</div>
                    </sw-block>
                </div>
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-started-chain', {
            template: `
                {% block twig_started_condition_block %}
                    {% parent %}
                    <h1 v-if="conditionFromPluginOne" class="plugin-one-condition">Plugin one</h1>
                {% endblock %}
            `,
        });

        ComponentFactory.override('native-block-legacy-twig-started-chain', {
            template: `
                {% block twig_started_condition_block %}
                    {% parent %}
                    <h1 v-else class="plugin-two-fallback">Plugin two fallback</h1>
                {% endblock %}
            `,
        });

        const wrapper = await withMutedConsoleWarn(() => {
            return mountNativeBlockComponent('native-block-legacy-twig-started-chain');
        });
        const branches = [
            '.plugin-one-condition',
            '.plugin-two-fallback',
        ];

        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, '.plugin-two-fallback');

        wrapper.vm.conditionFromPluginOne = true;
        await nextTick();
        await settleLegacyChain(wrapper);

        expectOnlyBranch(wrapper, branches, '.plugin-one-condition');
    });
});
