/** @sw-package framework */
import { isRef, shallowRef, type Ref } from 'vue';

type State = Record<string, Ref<unknown>>;

/**
 * Keep author-to-author calls and captured callbacks connected to the effective override state.
 * Before attachment, lazy source getters preserve ordinary initialization and temporal-dead-zone errors.
 * @private
 */
export function createSetupDispatch() {
    const epoch = shallowRef(0);
    let state: State | undefined;
    const forwards = new Map<string, unknown>();

    function read<T>(name: string, original: () => T): T {
        void epoch.value;
        const source = original();
        if (typeof source === 'function') {
            if (!forwards.has(name)) {
                forwards.set(
                    name,
                    new Proxy(source, {
                        apply(_target, receiver, args) {
                            const effective = state?.[name]?.value ?? original();
                            return Reflect.apply(effective as (...values: unknown[]) => unknown, receiver, args);
                        },
                        construct(_target, args, newTarget) {
                            return Reflect.construct(
                                (state?.[name]?.value ?? original()) as new (...values: unknown[]) => object,
                                args,
                                newTarget,
                            ) as object;
                        },
                    }),
                );
            }
            return forwards.get(name) as T;
        }
        if (isRef(source)) {
            if (!forwards.has(name)) {
                forwards.set(
                    name,
                    new Proxy(source, {
                        get(target, key, receiver) {
                            if (key !== 'value') return Reflect.get(target, key, receiver) as unknown;
                            void epoch.value;
                            return state?.[name] ? state[name].value : (original() as Ref<unknown>).value;
                        },
                        set(target, key, value, receiver) {
                            if (key !== 'value') return Reflect.set(target, key, value, receiver);
                            if (state?.[name]) state[name].value = value;
                            else (original() as Ref<unknown>).value = value;
                            return true;
                        },
                    }),
                );
            }
            return forwards.get(name) as T;
        }
        return (state?.[name] ? state[name].value : source) as T;
    }

    return {
        read,
        binding<T>(name: string, original: () => T, assign: (value: T) => void) {
            return {
                get value(): T {
                    return read(name, original);
                },
                set value(value: T) {
                    assign(value);
                    if (state?.[name]) state[name].value = value;
                },
            };
        },
        attach(effective: State) {
            state = effective;
            // Invalidate computeds and watches which evaluated during setup, before overrides existed.
            epoch.value += 1;
        },
    };
}
