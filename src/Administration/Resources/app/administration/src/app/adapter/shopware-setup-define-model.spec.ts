/**
 * @sw-package framework
 *
 * End-to-end coverage for `defineModel()` in a native setup component: the real Jest Vue transformer
 * lowers the fixtures, so these assertions run against the generated footer rather than a hand-written
 * imitation of it. What matters here is the round trip the transform specs cannot see - a write to the
 * re-declared model binding still reaches the parent as an `update:` event, from the template, from a
 * parent template ref, and through an override that replaces the binding.
 */

import { defineComponent, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { overrideComponentSetup } from 'src/app/adapter/composition-extension-system';
import resetCompositionOverrides from 'test/_helper_/reset-composition-overrides';
import ShopwareSetupModelFixture from './_mocks_/sw-jest-model-fixture.vue';
import ShopwareSetupModelFixtureOverride from './_mocks_/sw-jest-model-fixture.override.vue';

describe('src/app/adapter native setup defineModel', () => {
    beforeEach(resetCompositionOverrides);

    function createParent() {
        return defineComponent({
            components: {
                ShopwareSetupModelFixture,
            },
            setup() {
                return {
                    text: ref('hello'),
                };
            },
            template: '<shopware-setup-model-fixture ref="child" v-model="text" />',
        });
    }

    it('renders the model prop and emits the update when the template writes the binding', async () => {
        const wrapper = mount(ShopwareSetupModelFixture, {
            props: {
                modelValue: 'hello',
            },
        });

        await flushPromises();

        expect(wrapper.get('input').element.value).toBe('hello');

        await wrapper.get('[data-testid="default-model"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['hello!']]);
    });

    it('applies the default of a named model and emits under that model name', async () => {
        const wrapper = mount(ShopwareSetupModelFixture);

        await flushPromises();

        // The named model's default reaches the template through the footer's re-declared binding.
        expect(wrapper.get('[data-testid="headline-model"]').text()).toBe('untitled');

        await wrapper.setProps({ headline: 'Headline' });

        expect(wrapper.get('[data-testid="headline-model"]').text()).toBe('Headline');

        await wrapper.get('[data-testid="headline-model"]').trigger('click');

        expect(wrapper.emitted('update:headline')).toEqual([['Headline!']]);
    });

    it('round-trips a parent v-model binding', async () => {
        const wrapper = mount(createParent());

        await flushPromises();
        await wrapper.get('[data-testid="default-model"]').trigger('click');
        await flushPromises();

        expect(wrapper.vm.text).toBe('hello!');
    });

    it('lets a parent write the model through a template ref', async () => {
        const wrapper = mount(createParent());

        await flushPromises();

        const child = wrapper.vm.$refs.child as Record<string, unknown>;

        // `modelValue` is a swDefinePublic() entry, so it is exposed as a writable binding and the write
        // travels back out as the model update.
        expect(child.modelValue).toBe('hello');

        child.modelValue = 'written';
        await flushPromises();

        expect(wrapper.vm.text).toBe('written');
    });

    it('keeps the model writable when an override replaces it with a plain ref', async () => {
        overrideComponentSetup()('sw-jest-model-fixture' as never, () => ({ modelValue: ref('overridden') }) as never);

        const wrapper = mount(createParent());

        await flushPromises();

        // A plain ref replacement is two-way synced with the model ref, so the override's own value is
        // pushed out to the parent on mount and later writes keep travelling the same way.
        expect(wrapper.vm.text).toBe('overridden');

        await wrapper.get('[data-testid="default-model"]').trigger('click');
        await flushPromises();

        expect(wrapper.vm.text).toBe('overridden!');
    });

    it('routes writes through an override that replaces the model binding', async () => {
        mount(ShopwareSetupModelFixtureOverride);

        const wrapper = mount(createParent());

        await flushPromises();
        await wrapper.get('[data-testid="default-model"]').trigger('click');
        await flushPromises();

        // The override's setter upper-cases before writing the model, and the model still emits.
        expect(wrapper.vm.text).toBe('HELLO!');
    });
});
