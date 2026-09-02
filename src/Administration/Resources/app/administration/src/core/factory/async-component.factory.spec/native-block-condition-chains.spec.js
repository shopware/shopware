/**
 * @sw-package framework
 */

import { nextTick } from 'vue';
import {
    ComponentFactory,
    expectOnlyBranch,
    mountNativeBlockComponent,
    setupComponentFactoryHooks,
} from './native-block-condition.fixtures';

describe('core/factory/async-component.factory.ts - native block condition chains', () => {
    setupComponentFactoryHooks();

    it('renders the legacy else case without compiler errors', async () => {
        ComponentFactory.register('native-block-legacy-else', {
            data() {
                return {
                    isConditionTrue: false,
                };
            },
            template: `
                <div>
                    <sw-block name="test-block" :data="{}">
                        <div v-if="isConditionTrue" class="true-case">true</div>
                    </sw-block>

                    <sw-block extends="test-block">
                        <sw-block-parent />
                        <div v-else class="false-case">false</div>
                    </sw-block>
                </div>
            `,
        });

        const wrapper = await mountNativeBlockComponent('native-block-legacy-else');

        expect(wrapper.find('.true-case').exists()).toBe(false);
        expect(wrapper.find('.false-case').exists()).toBe(true);
    });

    // eslint-disable-next-line jest/expect-expect
    it('renders condition chains across more than two nested Twig component extensions', async () => {
        ComponentFactory.register('native-block-nested-twig-chain-base', {
            data() {
                return {
                    condition1: false,
                    condition2: false,
                    condition3: true,
                };
            },
            template: `
                <div>
                    {% block nested_twig_chain_root %}
                        <sw-block name="nested_twig_chain_block" :data="{}">
                            <div v-if="condition1" class="condition-one">Condition 1</div>
                        </sw-block>
                    {% endblock %}
                </div>
            `,
        });

        ComponentFactory.extend('native-block-nested-twig-chain-one', 'native-block-nested-twig-chain-base', {
            template: `
                {% block nested_twig_chain_root %}
                    {% parent %}
                    {% block nested_twig_chain_extension_one %}
                        <sw-block extends="nested_twig_chain_block">
                            <sw-block-parent />
                            <div v-else-if="condition2" class="condition-two">Condition 2</div>
                        </sw-block>
                    {% endblock %}
                {% endblock %}
            `,
        });

        ComponentFactory.extend('native-block-nested-twig-chain-two', 'native-block-nested-twig-chain-one', {
            template: `
                {% block nested_twig_chain_extension_one %}
                    {% parent %}
                    {% block nested_twig_chain_extension_two %}
                        <sw-block extends="nested_twig_chain_block">
                            <sw-block-parent />
                            <div v-else-if="condition3" class="condition-three">Condition 3</div>
                        </sw-block>
                    {% endblock %}
                {% endblock %}
            `,
        });

        const wrapper = await mountNativeBlockComponent('native-block-nested-twig-chain-two');
        const branches = [
            '.condition-one',
            '.condition-two',
            '.condition-three',
        ];

        expectOnlyBranch(wrapper, branches, '.condition-three');

        wrapper.vm.condition2 = true;
        await nextTick();
        await wrapper.vm.$nextTick();

        expectOnlyBranch(wrapper, branches, '.condition-two');

        wrapper.vm.condition1 = true;
        await nextTick();
        await wrapper.vm.$nextTick();

        expectOnlyBranch(wrapper, branches, '.condition-one');

        wrapper.vm.condition1 = false;
        wrapper.vm.condition2 = false;
        wrapper.vm.condition3 = false;
        await nextTick();
        await wrapper.vm.$nextTick();

        expectOnlyBranch(wrapper, branches, null);
    });

    it('preserves v-else-if chains that end inside a native block', async () => {
        const registerNativeBlockChain = (componentName, initialState) => {
            const blockName = `${componentName}-block`;

            ComponentFactory.register(componentName, {
                data() {
                    return initialState;
                },
                template: `
                    <div>
                        <sw-block name="${blockName}" :data="{}">
                            <div v-if="showBlue" class="blue-case">blue</div>
                            <div v-else-if="showGreen" class="green-case">green</div>
                        </sw-block>

                        <sw-block extends="${blockName}">
                            <sw-block-parent />
                            <div v-else-if="showRed" class="red-case">red</div>
                        </sw-block>

                        <sw-block extends="${blockName}">
                            <sw-block-parent />
                            <div v-else class="fallback-case">fallback</div>
                        </sw-block>
                    </div>
                `,
            });
        };

        registerNativeBlockChain('native-block-legacy-else-if-fallback', {
            showBlue: false,
            showGreen: false,
            showRed: false,
        });

        registerNativeBlockChain('native-block-legacy-else-if-red', {
            showBlue: false,
            showGreen: false,
            showRed: true,
        });

        registerNativeBlockChain('native-block-legacy-else-if-green', {
            showBlue: false,
            showGreen: true,
            showRed: false,
        });

        const fallbackWrapper = await mountNativeBlockComponent('native-block-legacy-else-if-fallback');
        const redWrapper = await mountNativeBlockComponent('native-block-legacy-else-if-red');
        const greenWrapper = await mountNativeBlockComponent('native-block-legacy-else-if-green');

        expect(fallbackWrapper.find('.fallback-case').exists()).toBe(true);
        expect(redWrapper.find('.red-case').exists()).toBe(true);
        expect(redWrapper.find('.fallback-case').exists()).toBe(false);
        expect(greenWrapper.find('.green-case').exists()).toBe(true);
        expect(greenWrapper.find('.red-case').exists()).toBe(false);
        expect(greenWrapper.find('.fallback-case').exists()).toBe(false);
    });

    // eslint-disable-next-line jest/expect-expect
    it('continues a restarted native condition chain in a later block extension', async () => {
        ComponentFactory.register('native-block-legacy-restarted-chain', {
            data() {
                return {
                    showPrimary: false,
                    showSecondary: false,
                    showRestart: true,
                    showRestartAlternative: false,
                };
            },
            template: `
                <div>
                    <sw-block name="restarted_condition_block" :data="{}">
                        <div v-if="showPrimary" class="primary">primary</div>
                    </sw-block>

                    <sw-block extends="restarted_condition_block">
                        <sw-block-parent />
                        <div v-else-if="showSecondary" class="secondary">secondary</div>

                        <div class="chain-boundary"></div>

                        <div v-if="showRestart" class="restart">restart</div>
                        <div v-else-if="showRestartAlternative" class="alternative">alternative</div>
                    </sw-block>

                    <sw-block extends="restarted_condition_block">
                        <sw-block-parent />
                        <div v-else class="restart-fallback">restart fallback</div>
                    </sw-block>
                </div>
            `,
        });

        const wrapper = await mountNativeBlockComponent('native-block-legacy-restarted-chain');
        const branches = [
            '.primary',
            '.secondary',
            '.restart',
            '.alternative',
            '.restart-fallback',
        ];

        expectOnlyBranch(wrapper, branches, '.restart');

        wrapper.vm.showRestart = false;
        wrapper.vm.showRestartAlternative = true;
        await nextTick();
        await wrapper.vm.$nextTick();

        expectOnlyBranch(wrapper, branches, '.alternative');

        wrapper.vm.showRestartAlternative = false;
        await nextTick();
        await wrapper.vm.$nextTick();

        expectOnlyBranch(wrapper, branches, '.restart-fallback');
    });

    it('cleans native condition chains when the owning sw-block unmounts', async () => {
        ComponentFactory.register('native-block-lifecycle-cleanup', {
            data() {
                return {
                    showBaseCondition: false,
                };
            },
            template: `
                <div>
                    <sw-block name="lifecycle_cleanup_block" :data="{}">
                        <div v-if="showBaseCondition" class="base-condition">base</div>
                    </sw-block>

                    <sw-block extends="lifecycle_cleanup_block">
                        <sw-block-parent />
                        <div v-else class="extension-fallback">fallback</div>
                    </sw-block>
                </div>
            `,
        });

        const wrapper = await mountNativeBlockComponent('native-block-lifecycle-cleanup');
        const useLegacyConditionContext = (
            await import('src/app/component/structure/sw-block-override/shim/legacy-condition-context')
        ).default;
        const { legacyConditionContext } = useLegacyConditionContext();
        const chainKey = `${wrapper.vm.$.uid}:lifecycle_cleanup_block:0`;
        const branches = [
            '.base-condition',
            '.extension-fallback',
        ];

        expectOnlyBranch(wrapper, branches, '.extension-fallback');
        expect(legacyConditionContext[chainKey]).toBeDefined();

        wrapper.vm.showBaseCondition = true;
        await nextTick();
        await wrapper.vm.$nextTick();

        expectOnlyBranch(wrapper, branches, '.base-condition');
        expect(legacyConditionContext[chainKey]).toBeDefined();

        wrapper.unmount();

        expect(legacyConditionContext[chainKey]).toBeUndefined();
    });
});
