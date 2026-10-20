/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 *
 * Integrative tests for the Options API → Composition API Override Shim.
 *
 * These tests verify the **full end-to-end flow**: a real Vue component is
 * defined with `createExtendableSetup()` and mounted directly (no factory).
 * Legacy Options API overrides are converted via `convertOptionsApiOverrideToCompositionApi`
 * and pushed directly into `_overridesMap`, simulating what `createExtendableSetup`
 * does at mount time when it processes pending overrides from the override registry.
 * The mounted component's reactive watcher picks up the change and updates the DOM.
 *
 * This proves the full integration: convertOptionsApiOverrideToCompositionApi
 * → _overridesMap → createExtendableSetup watcher → DOM update.
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument */

import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import { createExtendableSetup, overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';
import { mount } from '@vue/test-utils';
import { ref, computed, reactive, defineComponent, nextTick, watch } from 'vue';

/**
 * Helper: simulates the shim path that createExtendableSetup executes at mount time.
 * In production, all overrides are registered via ComponentFactory.override() *before*
 * the Vue app mounts, and createExtendableSetup processes them on first mount.
 * In tests we push the converted override directly to _overridesMap so the reactive
 * watcher in createExtendableSetup picks up the change.
 */
async function applyOptionsOverride(componentName: string, config: any) {
    const spy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    const overrideFn = convertOptionsApiOverrideToCompositionApi(componentName, config);
    if (!_overridesMap[componentName]) {
        _overridesMap[componentName] = reactive([]);
    }
    _overridesMap[componentName].push(overrideFn);
    await flushPromises();
    spy.mockRestore();
}

describe('Options API Shim — Integrative Tests', () => {
    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        ComponentFactory._clearComponentHelper();
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
        ComponentFactory.markComponentTemplatesAsNotResolved();

        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
    });

    // ─── Methods override with $super ────────────────────────────────────────

    describe('Methods override with $super:', () => {
        it('should call original method and override logic when $super is used', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-super-single' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('0');

            // Legacy plugin override via ComponentFactory.override()
            await applyOptionsOverride('comp-super-single', {
                methods: {
                    increment() {
                        (this as any).$super('increment');
                        (this as any).count += 10;
                    },
                },
            });

            // Click the button — chain: original(+1) then override(+10)
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('11');
        });

        it('should allow $super to chain through multiple Options API overrides', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-super-chain' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);

            // Plugin A: $super then +10
            await applyOptionsOverride('comp-super-chain', {
                methods: {
                    increment() {
                        (this as any).$super('increment');
                        (this as any).count += 10;
                    },
                },
            });

            // Plugin B: $super then *2
            await applyOptionsOverride('comp-super-chain', {
                methods: {
                    increment() {
                        (this as any).$super('increment');
                        (this as any).count *= 2;
                    },
                },
            });

            // Click: original(0→1) → A(1→11) → B(11→22)
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('22');
        });
    });

    // ─── Computed override ───────────────────────────────────────────────────

    describe('Computed override:', () => {
        it('should replace a computed property and re-evaluate reactively when state changes', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="doubled">{{ doubled }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-computed-getter' }, () => {
                        const count = ref(1);
                        const doubled = computed(() => count.value * 2);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, doubled, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.doubled').text()).toBe('2');

            // Override: tripled instead of doubled
            await applyOptionsOverride('comp-computed-getter', {
                computed: {
                    doubled() {
                        return (this as any).count * 3;
                    },
                },
            });

            expect(wrapper.find('.doubled').text()).toBe('3'); // 1 * 3

            // Click increment → count=2, tripled=6
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('2');
            expect(wrapper.find('.doubled').text()).toBe('6'); // 2 * 3
        });

        it('should support computed with getter and setter via Options API override', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="adjusted">{{ adjustedCount }}</div>
                    <button class="set-btn" @click="setAdjusted">Set to 500</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-computed-setter' }, () => {
                        const count = ref(10);
                        const adjustedCount = computed({
                            get: () => count.value + 5,
                            set: (val: number) => {
                                count.value = val - 5;
                            },
                        });
                        const setAdjusted = () => {
                            adjustedCount.value = 200;
                        };

                        return { public: { count, adjustedCount, setAdjusted } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.adjusted').text()).toBe('15'); // 10 + 5

            // Override both the computed and the method
            await applyOptionsOverride('comp-computed-setter', {
                computed: {
                    adjustedCount: {
                        get() {
                            return (this as any).count + 100;
                        },
                        set(val: number) {
                            (this as any).count = val - 100;
                        },
                    },
                },
                methods: {
                    setAdjusted() {
                        (this as any).adjustedCount = 500;
                    },
                },
            });

            // Getter: 10 + 100 = 110
            expect(wrapper.find('.adjusted').text()).toBe('110');

            // Click: setter receives 500 → count = 500 - 100 = 400
            await wrapper.find('.set-btn').trigger('click');
            expect(wrapper.find('.count').text()).toBe('400');
            expect(wrapper.find('.adjusted').text()).toBe('500'); // 400 + 100
        });
    });

    // ─── Data override ───────────────────────────────────────────────────────

    describe('Data override:', () => {
        it('should override existing ref values via data()', async () => {
            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-data' }, () => {
                        const count = ref(1);

                        return { public: { count } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('1');

            await applyOptionsOverride('comp-data', {
                data() {
                    return { count: 42 };
                },
            });

            // syncRef writes 42 into the original ref
            expect(wrapper.find('.count').text()).toBe('42');
        });

        it('should keep reactivity after data override — button click updates the DOM', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-data-reactive' }, () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);

            await applyOptionsOverride('comp-data-reactive', {
                data() {
                    return { count: 42 };
                },
            });

            expect(wrapper.find('.count').text()).toBe('42');

            // The ref is still reactive after syncRef
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('43');
        });
    });

    // ─── Watch override ──────────────────────────────────────────────────────

    describe('Watch override:', () => {
        it('should fire a watcher when the watched state changes via user interaction', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="log">{{ log }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-watch' }, () => {
                        const count = ref(0);
                        const log = ref('init');
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, log, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.log').text()).toBe('init');

            await applyOptionsOverride('comp-watch', {
                watch: {
                    count(newVal: any) {
                        (this as any).log = `count: ${newVal}`;
                    },
                },
            });

            // Watcher not fired yet (count hasn't changed)
            expect(wrapper.find('.log').text()).toBe('init');

            // Click → count changes → watcher fires → log updates
            await wrapper.find('.inc').trigger('click');
            await nextTick();
            expect(wrapper.find('.count').text()).toBe('1');
            expect(wrapper.find('.log').text()).toBe('count: 1');
        });

        it('should support watch with immediate option', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="log">{{ log }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-watch-immediate' }, () => {
                        const count = ref(0);
                        const log = ref('init');
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, log, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);

            await applyOptionsOverride('comp-watch-immediate', {
                watch: {
                    count: {
                        handler(newVal: any) {
                            (this as any).log = `count: ${newVal}`;
                        },
                        immediate: true,
                    },
                },
            });

            // Immediate watcher fires with current value (0)
            expect(wrapper.find('.log').text()).toBe('count: 0');

            // Click → watcher fires again
            await wrapper.find('.inc').trigger('click');
            await nextTick();
            expect(wrapper.find('.log').text()).toBe('count: 1');
        });

        it('should not interfere when base component already has a watcher and override adds another watcher', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="base-log">{{ baseLog }}</div>
                    <div class="override-log">{{ overrideLog }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-watch-coexist' }, () => {
                        const count = ref(0);
                        const baseLog = ref('base-init');
                        const overrideLog = ref('override-init');
                        const increment = () => {
                            count.value += 1;
                        };

                        // Base component's own watcher
                        watch(count, (newVal) => {
                            baseLog.value = `base: ${newVal}`;
                        });

                        return { public: { count, baseLog, overrideLog, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.base-log').text()).toBe('base-init');
            expect(wrapper.find('.override-log').text()).toBe('override-init');

            // Override adds its own watcher for the same `count` ref
            await applyOptionsOverride('comp-watch-coexist', {
                watch: {
                    count(newVal: any) {
                        (this as any).overrideLog = `override: ${newVal}`;
                    },
                },
            });

            // Click → both watchers must fire independently
            await wrapper.find('.inc').trigger('click');
            await nextTick();
            expect(wrapper.find('.count').text()).toBe('1');
            expect(wrapper.find('.base-log').text()).toBe('base: 1');
            expect(wrapper.find('.override-log').text()).toBe('override: 1');

            // Second click → both watchers fire again
            await wrapper.find('.inc').trigger('click');
            await nextTick();
            expect(wrapper.find('.count').text()).toBe('2');
            expect(wrapper.find('.base-log').text()).toBe('base: 2');
            expect(wrapper.find('.override-log').text()).toBe('override: 2');
        });
    });

    // ─── Combined override ───────────────────────────────────────────────────

    describe('Combined override (methods + computed + data):', () => {
        it('should apply methods, computed, and data from a single override object', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="doubled">{{ doubled }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-combined' }, () => {
                        const count = ref(0);
                        const doubled = computed(() => count.value * 2);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, doubled, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);

            await applyOptionsOverride('comp-combined', {
                data() {
                    return { count: 100 };
                },
                computed: {
                    doubled() {
                        return (this as any).count * 5;
                    },
                },
                methods: {
                    increment() {
                        (this as any).count += 50;
                    },
                },
            });

            expect(wrapper.find('.count').text()).toBe('100');
            expect(wrapper.find('.doubled').text()).toBe('500'); // 100 * 5

            // Click: override method adds 50
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('150');
            expect(wrapper.find('.doubled').text()).toBe('750'); // 150 * 5
        });
    });

    // ─── Mixed chain ─────────────────────────────────────────────────────────

    describe('Mixed override chain (Composition API + Options API):', () => {
        it('should coexist when a Composition API override is applied before the Options API override', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-mixed-comp-first' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('0');

            // 1. Composition API override: set count to 50
            overrideComponentSetup()('comp-mixed-comp-first', () => {
                return { count: ref(50) };
            });

            await flushPromises();
            expect(wrapper.find('.count').text()).toBe('50');

            // 2. Options API override via factory: replace increment to add 100
            await applyOptionsOverride('comp-mixed-comp-first', {
                methods: {
                    increment() {
                        (this as any).count += 100;
                    },
                },
            });

            // Click: override adds 100 to current count (50)
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('150');
        });

        it('should coexist when a Composition API override is applied after the Options API override', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-mixed-opts-first' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);

            // 1. Options API override via factory: replace increment to add 100
            await applyOptionsOverride('comp-mixed-opts-first', {
                methods: {
                    increment() {
                        (this as any).count += 100;
                    },
                },
            });

            // 2. Composition API override: set count to 50
            overrideComponentSetup()('comp-mixed-opts-first', () => {
                return { count: ref(50) };
            });

            await flushPromises();
            expect(wrapper.find('.count').text()).toBe('50');

            // Click: Options override adds 100 to current count (50)
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('150');
        });

        it('should chain $super correctly when the same method (increment) is overridden by both Composition API and Options API', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-same-method-chain' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('0');

            // 1. Composition API override: replaces increment to add 10 instead of 1
            overrideComponentSetup()('comp-same-method-chain', (previousState) => {
                return {
                    increment: () => {
                        (previousState as any).count.value += 10;
                    },
                };
            });

            await flushPromises();

            // Verify Composition API override is active
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('10');

            // 2. Options API override: calls $super (Composition override, +10) and additionally adds 100
            await applyOptionsOverride('comp-same-method-chain', {
                methods: {
                    increment() {
                        (this as any).$super('increment'); // calls Composition API override (+10)
                        (this as any).count += 100; // additionally adds 100
                    },
                },
            });

            // Click: $super calls Composition override (+10), Options override adds 100 → +110 per click
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('120'); // 10 (from first click) + 110
        });
    });

    // ─── Props access ────────────────────────────────────────────────────────

    describe('Props access in Options API override:', () => {
        it('should allow override methods to read component props via this', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="result">{{ result }}</div>
                    <button class="apply" @click="applyMultiplier">Apply</button>
                `,
                props: {
                    multiplier: { type: Number, default: 1 },
                },
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-props' }, () => {
                        const count = ref(5);
                        const result = computed(() => count.value * props.multiplier);
                        const applyMultiplier = () => {
                            count.value *= props.multiplier;
                        };

                        return { public: { count, result, applyMultiplier } };
                    }),
            });

            const wrapper = mount(originalComponent, { props: { multiplier: 3 } });
            expect(wrapper.find('.count').text()).toBe('5');
            expect(wrapper.find('.result').text()).toBe('15'); // 5 * 3

            // Override: the method reads this.multiplier from props
            await applyOptionsOverride('comp-props', {
                methods: {
                    applyMultiplier() {
                        (this as any).count *= (this as any).multiplier * 2;
                    },
                },
            });

            // Click: count = 5 * 3 * 2 = 30
            await wrapper.find('.apply').trigger('click');
            expect(wrapper.find('.count').text()).toBe('30');
            expect(wrapper.find('.result').text()).toBe('90'); // 30 * 3
        });
    });

    // ─── Mixin integration ───────────────────────────────────────────────────

    describe('Mixin integration:', () => {
        it('should merge mixin methods and data into the override', async () => {
            const helperMixin = {
                methods: {
                    double(n: number) {
                        return n * 2;
                    },
                },
                data() {
                    return { bonus: 100 };
                },
            };

            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="bonus">{{ bonus }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-mixin' }, () => {
                        const count = ref(0);
                        const bonus = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, bonus, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.bonus').text()).toBe('0');

            await applyOptionsOverride('comp-mixin', {
                mixins: [helperMixin],
                methods: {
                    increment() {
                        (this as any).count += (this as any).double((this as any).bonus);
                    },
                },
            });

            // Mixin data synced: bonus = 100
            expect(wrapper.find('.bonus').text()).toBe('100');

            // Click: count += double(100) = 200
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('200');
        });
    });

    // ─── Reactivity preservation ─────────────────────────────────────────────

    describe('Reactivity preservation after override:', () => {
        it('should maintain full reactivity across multiple sequential interactions', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <button class="inc" @click="increment">+</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-reactivity' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return { public: { count, increment } };
                    }),
            });

            const wrapper = mount(originalComponent);

            await applyOptionsOverride('comp-reactivity', {
                methods: {
                    increment() {
                        (this as any).$super('increment');
                        (this as any).count += 4;
                    },
                },
            });

            // Each click: original(+1) + override(+4) = +5
            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('5');

            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('10');

            await wrapper.find('.inc').trigger('click');
            expect(wrapper.find('.count').text()).toBe('15');
        });

        it('should keep two-way binding working after a data override on a text ref', async () => {
            const originalComponent = defineComponent({
                template: `
                    <input class="name-input" v-model="name" />
                    <div class="greeting">{{ greeting }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'comp-twoway' }, () => {
                        const name = ref('initial');
                        const greeting = computed(() => `Hello, ${name.value}`);

                        return { public: { name, greeting } };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.greeting').text()).toBe('Hello, initial');

            // Override: change name to 'World'
            await applyOptionsOverride('comp-twoway', {
                data() {
                    return { name: 'World' };
                },
            });

            expect(wrapper.find('.greeting').text()).toBe('Hello, World');
            expect((wrapper.find('.name-input').element as HTMLInputElement).value).toBe('World');

            // Type new value via v-model
            await wrapper.find('.name-input').setValue('Vue');
            expect(wrapper.find('.greeting').text()).toBe('Hello, Vue');
        });
    });
});
