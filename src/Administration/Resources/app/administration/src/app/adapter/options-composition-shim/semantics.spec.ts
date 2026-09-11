/**
 * @sw-package framework
 */
import { effectScope, nextTick, ref, computed } from 'vue';
import { convertOptionsApiOverrideToCompositionApi as convert } from '../options-composition-shim';

describe('Options override semantics', () => {
    let scope: ReturnType<typeof effectScope>;

    beforeEach(() => {
        scope = effectScope();
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        scope.stop();
        jest.restoreAllMocks();
    });

    it('calls data with the instance and vm argument after methods are available', () => {
        const state = scope.run(() =>
            convert('initialization', {
                methods: {
                    format(value: string) {
                        return `Hello ${value}`;
                    },
                },
                data(this: object, vm: object) {
                    const instance = this as { format: (value: string) => string };
                    return { greeting: instance.format((vm as { title: string }).title) };
                },
            })({}, { title: 'World' }),
        ) as { greeting: { value: string } };

        expect(state.greeting.value).toBe('Hello World');
    });

    it('runs beforeCreate before data and created after immediate watchers', () => {
        const events: string[] = [];
        scope.run(() =>
            convert('initialization-order', {
                beforeCreate() {
                    events.push('beforeCreate');
                },
                data() {
                    events.push('data');
                    return { count: 1 };
                },
                watch: {
                    count: {
                        immediate: true,
                        handler() {
                            events.push('watch');
                        },
                    },
                },
                created() {
                    events.push('created');
                },
            })({}, {}),
        );

        expect(events).toEqual([
            'beforeCreate',
            'data',
            'watch',
            'created',
        ]);
    });

    it('keeps watcher paths reactive across missing and replaced intermediate objects', async () => {
        const order = ref<{ customer?: { name: string } }>({});
        const changed = jest.fn<void, [unknown]>();
        scope.run(() => convert('path-watch', { watch: { 'order.customer.name': changed } })({ order }, {}));

        order.value.customer = { name: 'First' };
        await nextTick();
        order.value = { customer: { name: 'Second' } };
        await nextTick();

        expect(changed.mock.calls.map(([value]) => value)).toEqual([
            'First',
            'Second',
        ]);
    });

    it('resolves object string handlers and forwards watcher cleanup', async () => {
        const count = ref(0);
        const cleanup = jest.fn();
        const changed = jest.fn((_value, _old, onCleanup: (fn: () => void) => void) => onCleanup(cleanup));
        scope.run(() =>
            convert('handler-watch', {
                methods: { changed },
                watch: { count: { handler: 'changed', immediate: true } },
            })({ count }, {}),
        );

        count.value++;
        await nextTick();
        expect(changed).toHaveBeenCalledTimes(2);
        expect(cleanup).toHaveBeenCalledTimes(1);
        scope.stop();
        expect(cleanup).toHaveBeenCalledTimes(2);
    });

    it('preserves once watchers', async () => {
        const count = ref(0);
        const changed = jest.fn();
        scope.run(() =>
            convert('once-watch', {
                watch: { count: { handler: changed, once: true } },
            })({ count }, {}),
        );
        count.value++;
        await nextTick();
        count.value++;
        await nextTick();
        expect(changed).toHaveBeenCalledTimes(1);
    });

    it('uses later mixin methods and combines all same-key watchers', async () => {
        const count = ref(0);
        const first = jest.fn();
        const second = jest.fn();
        const own = jest.fn();
        const state = scope.run(() =>
            convert('mixin-order', {
                mixins: [
                    {
                        methods: {
                            label() {
                                return 'first';
                            },
                        },
                        watch: { count: first },
                    },
                    {
                        methods: {
                            label() {
                                return 'second';
                            },
                        },
                        watch: { count: second },
                    },
                ],
                watch: { count: own },
            })({ count }, {}),
        ) as { label: () => string };

        expect(state.label()).toBe('second');
        count.value++;
        await nextTick();
        expect(first).toHaveBeenCalledTimes(1);
        expect(second).toHaveBeenCalledTimes(1);
        expect(own).toHaveBeenCalledTimes(1);
    });

    it('includes nested extends before mixins and deduplicates identical lifecycle hooks', () => {
        const created = jest.fn();
        const state = scope.run(() =>
            convert('extends-options', {
                extends: {
                    methods: {
                        inherited() {
                            return 'parent';
                        },
                    },
                    created,
                },
                mixins: [{ created }],
                created,
            })({}, {}),
        ) as { inherited: () => string };

        expect(state.inherited()).toBe('parent');
        expect(created).toHaveBeenCalledTimes(1);
    });

    it('supports computed super getter and setter paths', () => {
        const value = ref('original');
        const field = computed({
            get: () => value.value,
            set: (next: string) => {
                value.value = next;
            },
        });
        const state = scope.run(() =>
            convert('computed-super', {
                computed: {
                    field: {
                        get(this: { $super: (name: string) => string }) {
                            return `${this.$super('field.get')}!`;
                        },
                        set(this: { $super: (name: string, value: string) => void }, next: string) {
                            this.$super('field.set', next);
                        },
                    },
                },
            })({ field }, {}),
        ) as { field: { value: string } };

        expect(state.field.value).toBe('original!');
        state.field.value = 'next';
        expect(value.value).toBe('next');
    });
});
