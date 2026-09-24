/**
 * @sw-package framework
 *
 * Compile-time checks of the macro declarations: `tsc` fails when they break.
 */

import type { Ref } from 'vue';

declare global {
    interface ComponentPublicApiMapping {
        'sw-macro-typed': { count: Ref<number> };
    }
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const expectType = <T>(value: T) => {};

// Never called: the macros exist only at compile time.
function typeChecks(): void {
    const typed = useSwPreviousState<'sw-macro-typed'>();
    expectType<number>(typed.count.value);
    // @ts-expect-error - the mapping types `count` as a number ref
    expectType<string>(typed.count.value);

    const shaped = useSwPreviousState<{ label: Ref<string> }>();
    expectType<string>(shaped.label.value);

    const unknownComponent = useSwPreviousState<'sw-not-mapped'>();
    expectType<unknown>(unknownComponent.anything);

    const untyped = useSwPreviousState();
    expectType<unknown>(untyped.anything);

    const bindings = { count: 1, label: 'x' };
    expectType<{ count: number; label: string }>(swDefinePublic(bindings));
}

describe('build/vue-setup-transform/shopware-setup-macros.d.ts', () => {
    it('is type-checked by tsc', () => {
        expect(typeof typeChecks).toBe('function');
    });
});
