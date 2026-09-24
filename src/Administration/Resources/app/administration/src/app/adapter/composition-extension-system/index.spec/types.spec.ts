/**
 * @sw-package framework
 *
 * Compile-time checks of the public types. `tsc` fails when they break; the runtime assertions are incidental.
 */

import type { ComputedRef, EmitFn, PropType, Ref, SetupContext, Slot } from 'vue';
import { computed, defineComponent, ref } from 'vue';
import { createExtendableSetup, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';

declare global {
    interface ComponentPublicApiMapping {
        'sw-typed-test-component': {
            baseValue: Ref<number>;
            multipliedValue: ComputedRef<number>;
            title: Ref<string>;
        };
    }
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const expectType = <T>(expression: T) => {};

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const TypedComponent = defineComponent({
    template: '<div />',
    props: {
        multiplier: { type: Number, default: 1 },
        complexProp: {
            type: Object as PropType<{ hello: string; world: number }>,
            default: () => ({ hello: '', world: 0 }),
        },
    },
    setup: (props, context) =>
        createExtendableSetup({ props, context, name: 'sw-typed-test-component' }, () => {
            expectType<number>(props.multiplier);
            expectType<{ hello: string; world: number }>(props.complexProp);

            const baseValue = ref(1);

            return {
                public: {
                    baseValue,
                    multipliedValue: computed(() => baseValue.value * props.multiplier),
                    title: ref('Title'),
                },
                private: { privateValue: ref('private') },
            };
        }),
});

describe('src/app/adapter/composition-extension-system: public types', () => {
    it('types the previous state from ComponentPublicApiMapping', () => {
        overrideComponentSetup<typeof TypedComponent>()('sw-typed-test-component', (previousState) => {
            expectType<number>(previousState.baseValue.value);
            expectType<number>(previousState.multipliedValue.value);
            expectType<string>(previousState.title.value);
            // @ts-expect-error - private bindings are not part of the typed public state
            expectType<string>(previousState.privateValue); // eslint-disable-line @typescript-eslint/no-unsafe-argument

            return {};
        });
    });

    it('types the props from the original component', () => {
        overrideComponentSetup<typeof TypedComponent>()('sw-typed-test-component', (previousState, props) => {
            expectType<number | undefined>(props.multiplier);
            expectType<{ hello: string; world: number } | undefined>(props.complexProp);

            return {};
        });
    });

    it('types the setup context', () => {
        overrideComponentSetup()('sw-typed-test-component', (previousState, props, context) => {
            expectType<Readonly<{ [name: string]: Slot | undefined }>>(context.slots);
            expectType<Record<string, unknown>>(context.attrs);
            expectType<EmitFn<unknown>>(context.emit);
            expectType<SetupContext>(context);

            return {};
        });
    });

    it('returns refs of the public and private bindings', () => {
        const result = createExtendableSetup(
            { props: { multiplier: 2 }, context: {}, name: 'sw-typed-test-component' },
            () => ({
                public: { baseValue: ref(1), multipliedValue: computed(() => 2), title: ref('Title') },
                private: { privateValue: ref('private') },
            }),
        );

        expectType<number>(result.baseValue.value);
        expectType<number>(result.multipliedValue.value);
        expectType<string>(result.privateValue.value);
    });

    it('falls back to an untyped state for components without a mapping entry', () => {
        overrideComponentSetup()('sw-untyped-component', (previousState) => {
            expectType<Record<string, unknown>>(previousState);

            return {};
        });
    });
});
