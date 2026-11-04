/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import type { Component } from 'vue';
import { transformShopwareSetupSfc } from '../../../../build/vue-setup-transform';
import resetCompositionOverrides from 'test/_helper_/reset-composition-overrides';
import 'src/app/store/block-override.store';
import createDataScopeFixture from 'src/app/component/structure/sw-block-override/sw-block-override.spec/test-utils/create-data-scope-fixture';
import BlockBase from '../_mocks_/sw-jest-transform-block-fixture.vue';

type HmrRuntime = { reload: (id: string, component: unknown) => void };

const runtime = (globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__: HmrRuntime }).__VUE_HMR_RUNTIME__;
const overrideFile = `${__dirname}/../_mocks_/sw-jest-transform-block-fixture.override.vue`;
const overrideId = 'sw-jest-transform-block-fixture:override';
const vueJest = jest.requireActual<{
    process: (source: string, filename: string, config: unknown, options: unknown) => { code: string };
}>('@vue/vue3-jest');

function compileOverride(label: string, version: string): Component {
    const source = `<template>
    <sw-block extends="sw_jest_transform_block_fixture_body">
        <p class="label">{{ label }}</p>
        <input v-model="draft" class="draft" />
    </sw-block>
</template>

<script setup>
import { ref } from 'vue';

const label = '${label}';
const draft = ref('');

swDefineOverride({});
</script>`;
    const transformed = transformShopwareSetupSfc(source, overrideFile, { hotUpdates: true });
    const { code } = vueJest.process(transformed!.code, overrideFile, { config: {} }, { instrument: false });
    const module = { exports: {} as { default: Component } };
    // eslint-disable-next-line @typescript-eslint/no-implied-eval -- evaluates the compiled SFC as Jest would load it
    const evaluate = new Function('require', 'module', 'exports', code) as (...args: unknown[]) => void;
    evaluate(require, module, module.exports);

    return Object.assign(module.exports.default, {
        __hmrId: overrideId,
        __shopwareSetupHmr: {
            componentName: 'sw-jest-transform-block-fixture',
            mode: 'override',
            version,
            data: {},
        },
    });
}

describe('src/app/adapter/composition-extension-system/script-hmr with transformed SFCs', () => {
    beforeEach(resetCompositionOverrides);

    it('applies a script edit to an override whose block reads script locals', async () => {
        const Base = Object.assign({}, BlockBase, { __hmrId: 'sw-jest-transform-block-fixture:base' });
        const Override = compileOverride('script v1', '1');
        const wrapper = mount(defineComponent({ setup: () => () => h('main', [h(Override), h(Base)]) }), {
            global: {
                components: { 'sw-block': await wrapTestComponent('sw-block', { sync: true }) },
                plugins: [createDataScopeFixture()],
            },
        });
        await flushPromises();
        expect(wrapper.get('.label').text()).toBe('script v1');

        runtime.reload(overrideId, compileOverride('script v2', '2'));
        await flushPromises();

        expect(wrapper.get('.label').text()).toBe('script v2');
    });
});
