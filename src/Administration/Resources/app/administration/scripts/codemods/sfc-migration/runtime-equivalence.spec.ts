/**
 * @sw-package framework
 */

import { createMemoryHistory, createRouter } from 'vue-router';
import { ref } from 'vue';
import { config, type VueWrapper } from '@vue/test-utils';
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
    ROUTE_WATCH_FIXTURE,
    SAFE_WATCH_FIXTURE,
    SIBLING_DATA_FIXTURE,
} from './runtime-equivalence-fixtures';
import SwBlock from 'src/app/component/structure/sw-block-override/sw-block/index';
import { ASYNC_CREATED } from './option-handlers';
import {
    convertFixture,
    flushPromises,
    loadGenerated,
    loadOriginal,
    mountBoth,
    mountComponent,
    setProbe,
} from './runtime-equivalence-harness';

const INJECT_TODO = 'array inject declaration requires runtime ref-unwrapping verification';
const DATA_THIS_TODO = 'data() initializer reads component this and is not runtime-equivalent';

type Vm = Record<string, unknown>;

function call(wrapper: VueWrapper, name: string, ...args: unknown[]): unknown {
    const method = (wrapper.vm as unknown as Vm)[name];

    if (typeof method !== 'function') {
        throw new Error(`${name} is not callable`);
    }

    return (method as (...values: unknown[]) => unknown)(...args);
}

function read(wrapper: VueWrapper, name: string): unknown {
    return (wrapper.vm as unknown as Vm)[name];
}

describe('SFC migration runtime equivalence', () => {
    afterEach(() => {
        delete (globalThis as typeof globalThis & { __runtimeEquivalenceProbe?: unknown }).__runtimeEquivalenceProbe;
    });

    it('executes arguments, named recursion, concise object returns, async and generator functions equivalently', async () => {
        const result = await convertFixture(FUNCTION_FIXTURE);

        expect(result).toMatchObject({ outcome: 'full', reasons: [] });

        const [original, generated] = await mountBoth(FUNCTION_FIXTURE, result);

        expect(call(generated, 'argumentsMethod', 2)).toBe(4);
        expect(call(original, 'argumentsMethod', 2)).toBe(4);
        expect(call(generated, 'recursive', 5)).toBe(120);
        expect(call(original, 'recursive', 5)).toBe(120);
        expect(call(generated, 'conciseObject')).toEqual(call(original, 'conciseObject'));
        expect(await call(generated, 'load', 'ready')).toBe('ready');
        expect(await call(original, 'load', 'ready')).toBe('ready');
        expect((call(generated, 'generator', 4) as Iterator<unknown>).next()).toEqual({ value: 8, done: false });
        expect((call(original, 'generator', 4) as Iterator<unknown>).next()).toEqual({ value: 8, done: false });
    });

    it.each([
        [PARAMETERIZED_DATA_FIXTURE, ['parameterized data() requires an explicit vm mapping']],
        [SIBLING_DATA_FIXTURE, [DATA_THIS_TODO]],
        [
            DATA_DEPENDENCY_FIXTURE,
            [
                INJECT_TODO,
                DATA_THIS_TODO,
                DATA_THIS_TODO,
                DATA_THIS_TODO,
                DATA_THIS_TODO,
            ],
        ],
    ])('keeps data initialization that reads the instance a draft: %#', async (fixture, reasons) => {
        const result = await convertFixture(fixture);

        expect(result).toMatchObject({ outcome: 'partial', reasons });
        expect(result.sfc).toContain('TODO(sfc-migration)');
    });

    it('reads props and injections in data() the same way, but keeps the draft partial', async () => {
        const result = await convertFixture(PROP_INJECT_DATA_FIXTURE);

        expect(result).toMatchObject({ outcome: 'partial', reasons: [INJECT_TODO, DATA_THIS_TODO, DATA_THIS_TODO] });

        const [original, generated] = await mountBoth(PROP_INJECT_DATA_FIXTURE, result, {
            props: { seed: 'prop' },
            provide: { service: 'service' },
        });

        expect(read(original, 'fromProp')).toBe('prop');
        expect(read(generated, 'fromProp')).toBe('prop');
        expect(read(original, 'fromInject')).toBe('service');
        expect(read(generated, 'fromInject')).toBe('service');
    });

    it('keeps array injection partial: a provided primitive reads the same, a provided ref does not', async () => {
        const result = await convertFixture(INJECTION_FIXTURE);

        expect(result).toMatchObject({ outcome: 'partial', reasons: [INJECT_TODO] });

        const [primitiveOriginal, primitiveGenerated] = await mountBoth(INJECTION_FIXTURE, result, {
            provide: { provided: 1 },
        });

        expect(call(primitiveOriginal, 'read')).toBe(1);
        expect(call(primitiveGenerated, 'read')).toBe(1);

        const provided = ref(1);
        const [refOriginal, refGenerated] = await mountBoth(INJECTION_FIXTURE, result, { provide: { provided } });

        // The Options API unwraps an injected ref on read; the setup binding hands out the ref itself.
        expect(call(refOriginal, 'read')).toBe(1);
        expect(call(refGenerated, 'read')).toBe(provided);
    });

    // A hyphenated watch key reads `this['foo-bar']`, which prop normalization never defines, so it
    // fires on neither side.
    it('executes hyphenated and nested watch paths equivalently', async () => {
        const result = await convertFixture(SAFE_WATCH_FIXTURE);

        expect(result).toMatchObject({ outcome: 'full', reasons: [] });

        const [original, generated] = await mountBoth(SAFE_WATCH_FIXTURE, result, { props: { 'foo-bar': 'initial' } });

        for (const wrapper of [original, generated]) {
            await wrapper.setProps({ 'foo-bar': 'next' });
            (read(wrapper, 'nested') as { value: number }).value = 2;
        }

        await flushPromises();

        expect(read(original, 'log')).toEqual(['nested:2']);
        expect(read(generated, 'log')).toEqual(['nested:2']);
    });

    it('converts a $route.<path> watcher and leaves the exact $route watcher as a TODO', async () => {
        const result = await convertFixture(ROUTE_WATCH_FIXTURE);

        expect(result).toMatchObject({
            outcome: 'partial',
            reasons: ["watch source '$route' has exact $route semantics that need runtime verification"],
        });

        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/one', name: 'one', component: { template: '<div />' } },
                { path: '/two', name: 'two', component: { template: '<div />' } },
            ],
        });

        await router.push('/one');
        await router.isReady();

        // The global test setup mocks `$route` with a static object, which would hide the router's.
        const globalMocks = config.global.mocks;
        let original: VueWrapper;
        let generated: VueWrapper;

        config.global.mocks = Object.fromEntries(
            Object.entries(globalMocks).filter(([key]) => key !== '$route' && key !== '$router'),
        );

        try {
            [original, generated] = await mountBoth(ROUTE_WATCH_FIXTURE, result, { plugins: [router] });
            await router.push('/two');
            await flushPromises();
        } finally {
            config.global.mocks = globalMocks;
        }

        expect(read(original, 'log')).toEqual(['name', 'route']);
        expect(read(generated, 'log')).toEqual(['name']);
    });

    it('does not confuse class-local this with component this', async () => {
        const result = await convertFixture(CLASS_THIS_FIXTURE);

        expect(result).toMatchObject({
            outcome: 'partial',
            reasons: ['`this.count` inside a nested function keeps its own `this`', 'dynamic `this[...]` access'],
        });

        const [original, generated] = await mountBoth(CLASS_THIS_FIXTURE, result);

        expect(call(original, 'readClassField')).toBeUndefined();
        expect(call(generated, 'readClassField')).toBeUndefined();
    });

    it('evaluates module-level code once per module, shared by every instance', async () => {
        const result = await convertFixture(MODULE_IDENTITY_FIXTURE);

        expect(result).toMatchObject({ outcome: 'full', reasons: [] });
        expect(result.module?.fileName).toBe(`${MODULE_IDENTITY_FIXTURE.name}.module.ts`);

        const probe = setProbe();
        const generated = await loadGenerated(MODULE_IDENTITY_FIXTURE, result);
        const [first, second] = [mountComponent(generated), mountComponent(generated)];

        expect(call(first, 'getShared')).toBe(call(second, 'getShared'));
        expect(probe).toEqual([call(first, 'getShared'), call(first, 'getShared')]);
    });

    it('preserves module regex identity, live getter timing and destructuring defaults', async () => {
        const result = await convertFixture(MODULE_BINDING_FIXTURE);

        expect(result).toMatchObject({ outcome: 'full', reasons: [] });

        type ModuleRead = { pattern: RegExp; getter: number; missing: number };
        const readTwice = async (component: Promise<unknown>): Promise<[ModuleRead, ModuleRead]> => {
            const loaded = (await component) as Parameters<typeof mountComponent>[0];

            return [
                call(mountComponent(loaded), 'readModule') as ModuleRead,
                call(mountComponent(loaded), 'readModule') as ModuleRead,
            ];
        };
        const originals = await readTwice(loadOriginal(MODULE_BINDING_FIXTURE));
        const generated = await readTwice(loadGenerated(MODULE_BINDING_FIXTURE, result));

        expect(generated[0].pattern).toBe(generated[1].pattern);
        expect(generated.map(({ getter }) => getter)).toEqual(originals.map(({ getter }) => getter));
        expect(generated.map(({ missing }) => missing)).toEqual([42, 42]);
    });

    it('refuses cross-block conditions that can execute side effects more than once', async () => {
        const result = await convertFixture(CROSS_BLOCK_SIDE_EFFECT_FIXTURE);

        expect(result).toEqual({
            outcome: 'skipped',
            reasons: ['cross-block conditional contains a side-effecting expression'],
            sfc: null,
            module: null,
        });
    });

    it.each([
        [CREATED_ONCE_FIXTURE, 'full', []],
        [CREATED_ASYNC_FIXTURE, 'partial', [ASYNC_CREATED]],
    ])('runs created exactly once: %#', async (fixture, outcome, reasons) => {
        const result = await convertFixture(fixture);

        expect(result).toMatchObject({ outcome, reasons });

        const originalProbe = setProbe();

        mountComponent(await loadOriginal(fixture));
        await flushPromises();

        const generatedProbe = setProbe();

        mountComponent(await loadGenerated(fixture, result));
        await flushPromises();

        expect(originalProbe).toEqual(['created']);
        expect(generatedProbe).toEqual(['created']);
    });

    it.each([
        [true, []],
        [false, ['created']],
    ])('honours an early return in created (skip: %s)', async (skip, expected) => {
        const result = await convertFixture(CREATED_EARLY_RETURN_FIXTURE);

        expect(result).toMatchObject({ outcome: 'full', reasons: [] });

        const originalProbe = setProbe();

        mountComponent(await loadOriginal(CREATED_EARLY_RETURN_FIXTURE), { props: { skip } });

        const generatedProbe = setProbe();

        mountComponent(await loadGenerated(CREATED_EARLY_RETURN_FIXTURE, result), { props: { skip } });

        expect(originalProbe).toEqual(expected);
        expect(generatedProbe).toEqual(expected);
    });

    it('keeps a created() local that shadows a member a draft', async () => {
        const result = await convertFixture(CREATED_LOCAL_COLLISION_FIXTURE);

        expect(result).toMatchObject({ outcome: 'partial', reasons: ['this.ready is shadowed by a local binding'] });
    });

    it('reports a synchronous created throw through the app error handler on both sides', async () => {
        const result = await convertFixture(CREATED_THROW_FIXTURE);

        expect(result).toMatchObject({ outcome: 'full', reasons: [] });

        const error = new Error('runtime-equivalence-created-throw');

        for (const component of [
            await loadOriginal(CREATED_THROW_FIXTURE),
            await loadGenerated(CREATED_THROW_FIXTURE, result),
        ]) {
            const errors: unknown[] = [];

            // Test utils rethrows what the handler saw during mount.
            expect(() => mountComponent(component, { errorHandler: (thrown) => errors.push(thrown) })).toThrow(error);
            expect(errors).toEqual([error]);
        }
    });

    // The inlined created() call is not one of Vue's hooks, so its rejection would surface as an
    // unhandled rejection instead of reaching the app error handler; the draft says so.
    it('keeps an async created() a draft, whose rejection the original reports to the error handler', async () => {
        const result = await convertFixture(CREATED_REJECT_FIXTURE);

        expect(result).toMatchObject({ outcome: 'partial', reasons: [ASYNC_CREATED] });
        expect(result.sfc).toContain(`TODO(sfc-migration) VERIFY: ${ASYNC_CREATED}`);

        const errors: unknown[] = [];

        mountComponent(await loadOriginal(CREATED_REJECT_FIXTURE), { errorHandler: (thrown) => errors.push(thrown) });
        await flushPromises();

        expect(errors).toEqual([new Error('runtime-equivalence-created-reject')]);
    });

    it('renders the converted block template with the data scope the setup transform binds', async () => {
        const result = await convertFixture(DATA_SCOPE_FIXTURE);

        expect(result).toMatchObject({ outcome: 'full', reasons: [] });

        const wrapper = mountComponent(await loadGenerated(DATA_SCOPE_FIXTURE, result, true), {
            useConvertedTemplate: true,
        });
        const block = wrapper.findComponent(SwBlock);

        expect(block.props('name')).toBe('sw_runtime_data_scope');
        expect((block.props('data') as { label?: unknown } | null)?.label).toBe('scope');
        expect(wrapper.find('span').exists()).toBe(true);
    });
});
