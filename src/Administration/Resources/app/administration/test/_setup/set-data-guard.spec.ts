/**
 * @sw-package framework
 */

/*
 * The guard under test is what makes `setData` on a native setup component loud, so these cases have to
 * call it there on purpose, and read the thrown error rather than await the promise.
 */
/* eslint-disable sw-test-rules/no-set-data, sw-test-rules/await-async-functions */

import { mount } from '@vue/test-utils';
import { ref } from 'vue';
import NativeSetupComponent from 'src/app/adapter/_mocks_/sw-jest-transform-fixture.vue';

const optionsApiComponent = {
    name: 'sw-options-api-host',
    template: '<div class="host">{{ count }}<slot /></div>',
    data() {
        return { count: 1 };
    },
};

describe('test/_setup/prepare_environment - setData guard', () => {
    it('throws instead of discarding the write on a native setup component', async () => {
        const wrapper = mount(NativeSetupComponent);
        await flushPromises();

        expect(() => wrapper.setData({ count: 99 })).toThrow(/setData\(\) does nothing/);
    });

    it('names the component and spells out the replacement', async () => {
        const wrapper = mount(NativeSetupComponent);
        await flushPromises();

        expect(() => wrapper.setData({ count: 99 })).toThrow('does nothing on sw-jest-transform-fixture');
        expect(() => wrapper.setData({ count: 99 })).toThrow('wrapper.vm.count = <value>;');
        expect(() => wrapper.setData({ count: 99 })).toThrow('await nextTick();');
    });

    it('leaves setData working on an Options API component', async () => {
        const wrapper = mount(optionsApiComponent);

        await wrapper.setData({ count: 99 });

        expect(wrapper.text()).toBe('99');
    });

    it('guards a child component reached through findComponent', async () => {
        const wrapper = mount({
            name: 'sw-options-api-parent',
            components: { 'sw-native-child': NativeSetupComponent },
            template: '<div><sw-native-child /></div>',
            data() {
                return { unused: true };
            },
        });
        await flushPromises();

        expect(() => wrapper.findComponent(NativeSetupComponent).setData({ count: 99 })).toThrow(/setData\(\) does nothing/);
    });

    it('throws for a key a plain setup() owns, which setData cannot reach either', () => {
        const wrapper = mount({
            name: 'sw-setup-returning-host',
            template: '<div>{{ label }}</div>',
            setup() {
                return { label: ref('before') };
            },
        });

        expect(() => wrapper.setData({ label: 'after' })).toThrow('keeps label in setupState');
    });

    it('still writes the keys a mixed component really keeps in data()', async () => {
        const wrapper = mount({
            name: 'sw-mixed-state-host',
            template: '<div>{{ fromData }}</div>',
            data() {
                return { fromData: 'before' };
            },
            setup() {
                return { fromSetup: ref('untouched') };
            },
        });

        await wrapper.setData({ fromData: 'after' });

        expect(wrapper.text()).toBe('after');
        expect(() => wrapper.setData({ fromSetup: 'after' })).toThrow('keeps fromSetup in setupState');
    });

    it('leaves an Options API host alone when it embeds a native setup child', async () => {
        const wrapper = mount({
            name: 'sw-options-api-parent',
            components: { 'sw-native-child': NativeSetupComponent },
            template: '<div class="host">{{ label }}<sw-native-child /></div>',
            data() {
                return { label: 'before' };
            },
        });
        await flushPromises();

        await wrapper.setData({ label: 'after' });

        expect(wrapper.find('.host').text()).toContain('after');
    });
});
