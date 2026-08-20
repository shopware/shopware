/**
 * @sw-package framework
 * @group disabledCompat
 */

import { defineComponent, h, ref, type Slot } from 'vue';
import { mount } from '@vue/test-utils';
import useBlockNodeStack from './use-block-node-stack';
import swBlockParent from '../sw-block-parent/index';

type DataScope = { label: string };

/**
 * Builds a minimal host around the composable, standing in for `sw-block` / `sw-native-block-host`.
 */
function createHost(resolveSlots: () => Slot[], data: DataScope = { label: 'scope' }) {
    return defineComponent({
        setup() {
            const template = useBlockNodeStack(resolveSlots, () => data);

            return { template };
        },
        render() {
            return this.template;
        },
    });
}

function marker(className: string): Slot {
    return () => [h('div', { class: className })];
}

describe('src/app/component/structure/sw-block-override/shared/use-block-node-stack', () => {
    it('renders only the last slot of the stack', () => {
        const wrapper = mount(
            createHost(() => [
                marker('first'),
                marker('second'),
                marker('third'),
            ]),
        );

        expect(wrapper.find('.third').exists()).toBe(true);
        expect(wrapper.find('.first').exists()).toBe(false);
        expect(wrapper.find('.second').exists()).toBe(false);
    });

    it('renders nothing for an empty stack', () => {
        const wrapper = mount(createHost(() => []));

        expect(wrapper.html()).toBe('');
    });

    it('passes the data scope to every slot', () => {
        const received: unknown[] = [];
        const recordingSlot: Slot = (data) => {
            received.push(data);

            return [h('div')];
        };

        mount(
            createHost(() => [
                recordingSlot,
                recordingSlot,
            ]),
        );

        expect(received).toEqual([
            { label: 'scope' },
            { label: 'scope' },
        ]);
    });

    it('lets sw-block-parent claim the slot below it', () => {
        const wrapper = mount(
            createHost(() => [
                marker('parent-content'),
                () => [
                    h(swBlockParent),
                    h('div', { class: 'extension' }),
                ],
            ]),
        );

        const children = wrapper.findAll('div');

        expect(children.map((child) => child.classes()[0])).toEqual([
            'parent-content',
            'extension',
        ]);
    });

    it('chains one sw-block-parent per stack entry', () => {
        const wrapper = mount(
            createHost(() => [
                marker('base'),
                () => [
                    h(swBlockParent),
                    h('div', { class: 'first-extension' }),
                ],
                () => [
                    h(swBlockParent),
                    h('div', { class: 'second-extension' }),
                ],
            ]),
        );

        expect(wrapper.findAll('div').map((child) => child.classes()[0])).toEqual([
            'base',
            'first-extension',
            'second-extension',
        ]);
    });

    it('re-renders when the resolved stack changes', async () => {
        const withExtension = ref(false);
        const wrapper = mount(createHost(() => (withExtension.value ? [marker('extension')] : [marker('base')])));

        expect(wrapper.find('.base').exists()).toBe(true);

        withExtension.value = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.extension').exists()).toBe(true);
        expect(wrapper.find('.base').exists()).toBe(false);
    });
});
