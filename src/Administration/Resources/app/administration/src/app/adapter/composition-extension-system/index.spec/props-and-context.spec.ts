/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access */

import { computed, defineComponent, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap, createExtendableSetup, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';
import { defineExtendable } from './test-utils';

const name = 'sw-props-and-context';

const createMultiplied = () =>
    defineExtendable(
        {
            name,
            template: '<div>{{ result }}</div>',
            props: { multiplier: { type: Number, default: 1 } },
        },
        (props: { multiplier: number }) => ({
            public: { result: computed(() => 2 * props.multiplier) },
        }),
    );

describe('src/app/adapter/composition-extension-system: props and setup context', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    it('passes the reactive props to an override', async () => {
        overrideComponentSetup()(name, (previousState, props: { multiplier?: number }) => ({
            result: computed(() => `${previousState.result.value as number} x ${props.multiplier ?? 0}`),
        }));

        const wrapper = mount(createMultiplied(), { props: { multiplier: 3 } });
        expect(wrapper.text()).toBe('6 x 3');

        await wrapper.setProps({ multiplier: 4 } as never);
        expect(wrapper.text()).toBe('8 x 4');
    });

    it('rejects an override result for a prop', () => {
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});

        overrideComponentSetup()(name, () => ({ multiplier: ref(10) }));

        const wrapper = mount(createMultiplied(), { props: { multiplier: 3 } });

        expect(wrapper.text()).toBe('6');
        expect(error).toHaveBeenCalledWith(
            '[sw-props-and-context] Override result value not working. Cannot override props. Following prop should be changed: "multiplier"',
        );
        error.mockRestore();
    });

    it('throws in development when a setup binding shares its name with a prop', () => {
        const component = defineComponent({
            template: '<div />',
            props: { title: String },
            setup: (props, context) =>
                createExtendableSetup({ props, context, name }, () => ({ public: { title: ref('binding') } })),
        });

        expect(() => mount(component)).toThrow(
            '[sw-props-and-context] Setup bindings must not share a name with a prop: "title".',
        );
    });

    it('passes the setup context to an override', () => {
        overrideComponentSetup()(name, (previousState, props, context) => ({
            message: ref(`${String(context.attrs.title)}, header slot: ${String(!!context.slots.header)}`),
        }));

        const component = defineExtendable({ name, template: '<div>{{ message }}</div>' }, () => ({
            public: { message: ref('base') },
        }));

        expect(mount(component, { attrs: { title: 'Title' } }).text()).toBe('Title, header slot: false');
        expect(mount(component, { attrs: { title: 'Title' }, slots: { header: 'Header' } }).text()).toBe(
            'Title, header slot: true',
        );
    });

    it('lets an override emit through the setup context', async () => {
        overrideComponentSetup()(name, (previousState, props, context) => ({
            save: () => context.emit('save', 'from override'),
        }));

        const component = defineExtendable({ name, template: '<button @click="save" />', emits: ['save'] }, () => ({
            public: { save: () => {} },
        }));
        const wrapper = mount(component);
        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('save')).toEqual([['from override']]);
    });

    it('falls back to the instance setup context when none is passed', () => {
        let receivedContext: unknown;

        overrideComponentSetup()(name, (previousState, props, context) => {
            receivedContext = context;

            return {};
        });

        const component = defineComponent({
            template: '<div />',
            setup: (props, context) => {
                createExtendableSetup({ props, name }, () => ({ public: {} }));

                return { context };
            },
        });

        const wrapper = mount(component);

        expect(receivedContext).toBe((wrapper.vm as unknown as { context: unknown }).context);
    });
});
