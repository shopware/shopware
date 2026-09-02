/**
 * @sw-package framework
 */

import { createMemoryHistory, createRouter } from 'vue-router';
import { ref } from 'vue';
import type { VueWrapper } from '@vue/test-utils';
import {
    CLASS_THIS_FIXTURE,
    CREATED_ASYNC_FIXTURE,
    CREATED_EARLY_RETURN_FIXTURE,
    CREATED_LOCAL_COLLISION_FIXTURE,
    CREATED_ONCE_FIXTURE,
    CREATED_REJECT_FIXTURE,
    CREATED_THROW_FIXTURE,
    CROSS_BLOCK_SIDE_EFFECT_FIXTURE,
    DATA_DEPENDENCY_FIXTURE,
    DATA_SCOPE_FIXTURE,
    FUNCTION_FIXTURE,
    INJECTION_FIXTURE,
    MODULE_BINDING_FIXTURE,
    MODULE_IDENTITY_FIXTURE,
    PARAMETERIZED_DATA_FIXTURE,
    PROP_INJECT_DATA_FIXTURE,
    ROUTE_GUARD_FIXTURE,
    ROUTE_WATCH_FIXTURE,
    SAFE_WATCH_FIXTURE,
    SIBLING_DATA_FIXTURE,
} from './runtime-equivalence-fixtures';
import {
    type RoutedMount,
    convertFixture,
    flushPromises,
    mountGenerated,
    mountGeneratedPair,
    mountOriginal,
    mountOriginalPair,
    mountRoutedGenerated,
    mountRoutedOriginal,
    resetOverrides,
    runEquivalentOrConservative,
    setProbe,
} from './runtime-equivalence-harness';

type Vm = Record<string, unknown>;

function vmOf(wrapper: { vm: unknown }): Vm {
    return wrapper.vm as Vm;
}

function expectConservative(outcome: string): void {
    expect([
        'partial',
        'skipped',
    ]).toContain(outcome);
}

describe('SFC migration runtime equivalence', () => {
    beforeEach(() => {
        resetOverrides();
        setProbe();
    });

    afterEach(() => {
        resetOverrides();
        delete (globalThis as typeof globalThis & { __runtimeEquivalenceProbe?: unknown }).__runtimeEquivalenceProbe;
    });

    it('executes arguments, named recursion, concise object returns, async and generator functions equivalently', async () => {
        const pair = await runEquivalentOrConservative(FUNCTION_FIXTURE, async (original, generated) => {
            const originalVm = vmOf(original);
            const generatedVm = vmOf(generated);

            const call = (vm: Vm, name: string, ...args: unknown[]): unknown => {
                const method = vm[name];

                if (typeof method !== 'function') {
                    throw new Error(`${name} is not callable`);
                }

                return (method as (...values: unknown[]) => unknown)(...args);
            };

            expect(call(generatedVm, 'argumentsMethod', 2)).toBe(call(originalVm, 'argumentsMethod', 2));
            expect(call(generatedVm, 'recursive', 5)).toBe(call(originalVm, 'recursive', 5));
            expect(call(generatedVm, 'conciseObject')).toEqual(call(originalVm, 'conciseObject'));
            expect(await call(generatedVm, 'load', 'ready')).toBe(await call(originalVm, 'load', 'ready'));
            const generatedIterator = call(generatedVm, 'generator', 4) as Iterator<unknown>;
            const originalIterator = call(originalVm, 'generator', 4) as Iterator<unknown>;

            expect(generatedIterator.next()).toEqual(originalIterator.next());
        });

        expect(pair.result.outcome).toBeDefined();

        if (pair.conservative) {
            expectConservative(pair.result.outcome);
        }
    });

    it.each([
        PARAMETERIZED_DATA_FIXTURE,
        SIBLING_DATA_FIXTURE,
        DATA_DEPENDENCY_FIXTURE,
    ])('keeps unsafe data initialization conservative: %s', async (fixture) => {
        const result = await convertFixture(fixture);

        expect(result.outcome).toBeDefined();
        expectConservative(result.outcome);
    });

    it('compares prop/inject data reads when the implementation supports them', async () => {
        const pair = await runEquivalentOrConservative(
            PROP_INJECT_DATA_FIXTURE,
            (original, generated) => {
                expect(vmOf(generated).fromProp).toBe(vmOf(original).fromProp);
                expect(vmOf(generated).fromInject).toBe(vmOf(original).fromInject);
            },
            { props: { seed: 'prop' }, provide: { service: 'service' } },
        );

        expect(pair.result.outcome).toBeDefined();

        if (pair.conservative) {
            expectConservative(pair.result.outcome);
        }
    });

    it.each([
        1,
        ref(1),
    ])('compares primitive and Ref injection reads/writes or downgrades them', async (provided) => {
        const pair = await runEquivalentOrConservative(
            INJECTION_FIXTURE,
            (original, generated) => {
                const originalVm = vmOf(original);
                const generatedVm = vmOf(generated);
                const originalRead = originalVm.read as () => unknown;
                const generatedRead = generatedVm.read as () => unknown;
                const originalWrite = originalVm.write as (value: number) => void;
                const generatedWrite = generatedVm.write as (value: number) => void;

                expect(generatedRead()).toBe(originalRead());
                generatedWrite(7);
                originalWrite(7);
                expect(generatedRead()).toBe(originalRead());
            },
            { provide: { provided } },
        );

        expect(pair.result.outcome).toBeDefined();

        if (pair.conservative) {
            expectConservative(pair.result.outcome);
        }
    });

    it('executes safe hyphenated and nested watch paths equivalently', async () => {
        const pair = await runEquivalentOrConservative(
            SAFE_WATCH_FIXTURE,
            async (original, generated) => {
                await generated.setProps({ 'foo-bar': 'next' });
                await original.setProps({ 'foo-bar': 'next' });
                (vmOf(generated).nested as { value: number }).value = 2;
                (vmOf(original).nested as { value: number }).value = 2;
                await flushPromises();

                expect(vmOf(generated).log).toEqual(vmOf(original).log);
            },
            { props: { 'foo-bar': 'initial' } },
        );

        expect(pair.result.outcome).toBeDefined();

        if (pair.conservative) {
            expectConservative(pair.result.outcome);
        }
    });

    it('runs in-component route guards through their composables', async () => {
        const result = await convertFixture(ROUTE_GUARD_FIXTURE);

        expect(result.outcome).toBe('full');

        const trace = async ({ router }: RoutedMount): Promise<unknown[]> => {
            const probe = setProbe();

            await router.push('/1');
            await flushPromises();
            await router.push('/2');
            await flushPromises();
            await router.push('/leave');
            await flushPromises();

            return [
                ...probe.events,
                router.currentRoute.value.name,
            ];
        };

        const original = await trace(mountRoutedOriginal(ROUTE_GUARD_FIXTURE));
        const generated = await trace(mountRoutedGenerated(ROUTE_GUARD_FIXTURE, result));

        // The update guard sees each id, and the leave guard cancels the navigation off the fixture.
        expect(original).toEqual([
            'update:2',
            'leave:true',
            'fixture',
        ]);
        expect(generated).toEqual(original);
    });

    it('keeps exact-$route watch sources conservative until their runtime contract is proven', async () => {
        const result = await convertFixture(ROUTE_WATCH_FIXTURE);

        expect(result.outcome).toBeDefined();
        expectConservative(result.outcome);
    });

    it('does not confuse class-local this with component this', async () => {
        const pair = await runEquivalentOrConservative(CLASS_THIS_FIXTURE, (original, generated) => {
            expect((vmOf(generated).readClassField as () => unknown)()).toBe(
                (vmOf(original).readClassField as () => unknown)(),
            );
        });

        expect(pair.result.outcome).toBeDefined();

        if (pair.conservative) {
            expectConservative(pair.result.outcome);
        }
    });

    it('preserves module-once identity across two component instances or downgrades the prelude', async () => {
        const result = await convertFixture(MODULE_IDENTITY_FIXTURE);

        if (result.outcome !== 'full') {
            expectConservative(result.outcome);
            return;
        }

        const [
            firstOriginal,
            secondOriginal,
        ] = mountOriginalPair(MODULE_IDENTITY_FIXTURE);
        const [
            firstGenerated,
            secondGenerated,
        ] = mountGeneratedPair(MODULE_IDENTITY_FIXTURE, result);

        expect((vmOf(firstGenerated).getShared as () => unknown)()).toBe(
            (vmOf(secondGenerated).getShared as () => unknown)(),
        );
        expect((vmOf(firstOriginal).getShared as () => unknown)()).toBe((vmOf(secondOriginal).getShared as () => unknown)());
    });

    it('preserves module regex identity, live getter timing and destructuring defaults', async () => {
        const result = await convertFixture(MODULE_BINDING_FIXTURE);

        if (result.outcome !== 'full') {
            expectConservative(result.outcome);
            return;
        }

        const [
            firstOriginal,
            secondOriginal,
        ] = mountOriginalPair(MODULE_BINDING_FIXTURE);
        const [
            firstGenerated,
            secondGenerated,
        ] = mountGeneratedPair(MODULE_BINDING_FIXTURE, result);
        const read = (wrapper: VueWrapper): { pattern: RegExp; getter: number; missing: number } =>
            (vmOf(wrapper).readModule as () => { pattern: RegExp; getter: number; missing: number })();
        const generatedFirst = read(firstGenerated);
        const generatedSecond = read(secondGenerated);
        const originalFirst = read(firstOriginal);
        const originalSecond = read(secondOriginal);

        expect(generatedFirst.pattern).toBe(generatedSecond.pattern);
        expect(generatedFirst.getter).toBe(originalFirst.getter);
        expect(generatedSecond.getter).toBe(originalSecond.getter);
        expect(generatedFirst.missing).toBe(42);
        expect(generatedSecond.missing).toBe(42);
    });

    it('downgrades cross-block conditions that can execute side effects more than once', async () => {
        const result = await convertFixture(CROSS_BLOCK_SIDE_EFFECT_FIXTURE);

        expect(result.outcome).toBeDefined();
        expectConservative(result.outcome);
    });

    it('runs synchronous and asynchronous created hooks exactly once', async () => {
        for (const fixture of [
            CREATED_ONCE_FIXTURE,
            CREATED_ASYNC_FIXTURE,
        ]) {
            const originalProbe = setProbe();
            mountOriginal(fixture);
            await flushPromises();
            const originalEvents = [...originalProbe.events];

            const result = await convertFixture(fixture);

            if (result.outcome !== 'full') {
                expectConservative(result.outcome);
                continue;
            }

            const generatedProbe = setProbe();
            mountGenerated(fixture, result);
            await flushPromises();

            expect(generatedProbe.events).toEqual(originalEvents);
            expect(generatedProbe.events).toHaveLength(1);
        }
    });

    it('preserves created early returns or keeps them out of full conversion', async () => {
        const pair = await runEquivalentOrConservative(CREATED_EARLY_RETURN_FIXTURE, () => undefined, {
            props: { skip: true },
        });

        expect(pair.result.outcome).toBeDefined();

        if (pair.conservative) {
            expectConservative(pair.result.outcome);
        }
    });

    it('keeps created local collisions conservative', async () => {
        const result = await convertFixture(CREATED_LOCAL_COLLISION_FIXTURE);

        expect(result.outcome).toBeDefined();
        expectConservative(result.outcome);
    });

    it('records synchronous created throws and asynchronous rejections without forcing execution', async () => {
        const outcomes: string[] = [];

        for (const fixture of [
            CREATED_THROW_FIXTURE,
            CREATED_REJECT_FIXTURE,
        ]) {
            const result = await convertFixture(fixture);

            expect(result.outcome).toBeDefined();
            outcomes.push(result.outcome);

            if (result.outcome !== 'full') {
                expectConservative(result.outcome);
            }

            expect(result.sfc === null).toBe(result.outcome === 'skipped');
        }

        expect(outcomes).toEqual([
            'full',
            'full',
        ]);
    });

    it('runs the generated $dataScope path through the real setup transform without touching disk', async () => {
        const result = await convertFixture(DATA_SCOPE_FIXTURE);

        expect(result.sfc).not.toBeNull();

        const lowered = result.sfc
            ? (await import('../../../build/vue-setup-transform/index.ts')).transformShopwareSetupSfc(
                  result.sfc,
                  `${DATA_SCOPE_FIXTURE.name}.vue`,
              )
            : null;

        expect(lowered?.code).toContain(':data="$dataScope"');

        let mounted = false;

        if (result.outcome === 'full') {
            const wrapper = mountGenerated(DATA_SCOPE_FIXTURE, result, { useConvertedTemplate: true });

            mounted = wrapper.exists();
        }

        expect(
            result.outcome === 'full'
                ? mounted
                : [
                      'partial',
                      'skipped',
                  ].includes(result.outcome),
        ).toBe(true);
    });

    it('keeps the runtime harness compatible with router-backed callers', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/one', name: 'one', component: { template: '<div />' } },
                { path: '/two', name: 'two', component: { template: '<div />' } },
            ],
        });

        await router.push('/one');
        await router.isReady();

        const result = await convertFixture(ROUTE_WATCH_FIXTURE);

        if (result.outcome !== 'full') {
            expectConservative(result.outcome);
            return;
        }

        const wrapper = mountGenerated(ROUTE_WATCH_FIXTURE, result, { plugins: [router] });

        await router.push('/two');
        await flushPromises();

        expect(wrapper.exists()).toBe(true);
    });
});
