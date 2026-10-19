/**
 * @sw-package framework
 */

import { mount, flushPromises } from '@vue/test-utils';
import { defineAsyncComponent, type ComponentPublicInstance } from 'vue';
import Feature from 'src/core/feature';
import { allowDeprecation, deprecations } from './deprecations';
import { callerFile, location, ownFrames } from './deprecationUsageSite';
import oldFunction, { oldWrappedFunction } from './_fixtures/deprecations/oldFunction';
import oldFunctionCaller from './_fixtures/deprecations/oldFunctionCaller';
import selfDeprecatingComponent from './_fixtures/deprecations/selfDeprecatingComponent';

// Registers the stand-in major of the fixtures, so the guard does not report it as a typo.
Feature.init({ V7_0_0_0: false });

const spec = 'test/_helper_/deprecationUsageSite.spec.ts';

const legacyComponent = { name: 'sw-legacy', template: '<div />', deprecated: 'v7.0.0.0' };
const card = { name: 'sw-card', template: '<section><slot /></section>' };

/**
 * The usage sites of the deprecations the current test recorded, in order.
 *
 * @example
 * expect(recordedSites()).toEqual(['sw-author > sw-legacy']);
 */
function recordedSites(): string[] {
    return deprecations.entries.map((entry) => entry.usageSite);
}

/**
 * Mounts `sw-author`, whose template renders the deprecated `sw-legacy` into the slot of `sw-card`.
 *
 * @example
 * mountDeprecatedComponentInASlot();
 */
function mountDeprecatedComponentInASlot(): void {
    mount({
        name: 'sw-author',
        components: { 'sw-legacy': legacyComponent, 'sw-card': card },
        template: '<sw-card><sw-legacy /></sw-card>',
    });
}

describe('test/_helper_/deprecationUsageSite', () => {
    beforeEach(() => {
        // Every test here runs into a deprecation on purpose and asserts where it is used.
        allowDeprecation(/is deprecated/);
    });

    describe('inside a running component', () => {
        it('names the slot author, not the component rendering the slot', () => {
            mountDeprecatedComponentInASlot();

            expect(recordedSites()).toEqual(['sw-author > sw-legacy']);
        });

        it('names a component wrapped by wrapTestComponent without the suffix', () => {
            mount({
                name: 'sw-author__wrapped',
                components: { 'sw-legacy': { ...legacyComponent, name: 'sw-legacy__wrapped' } },
                template: '<sw-legacy />',
            });

            expect(recordedSites()).toEqual(['sw-author > sw-legacy']);
        });

        it('skips the wrapper of an async component', async () => {
            mount({
                name: 'sw-author',
                components: { 'sw-legacy': defineAsyncComponent(() => Promise.resolve(legacyComponent)) },
                template: '<sw-legacy />',
            });
            await flushPromises();

            expect(recordedSites()).toEqual(['sw-author > sw-legacy']);
        });

        it('names only the component when the spec mounts it', () => {
            mount(legacyComponent);

            expect(recordedSites()).toEqual(['sw-legacy']);
        });

        it('names only the running component, not its author, for a deprecated function it calls', () => {
            const caller = {
                name: 'sw-caller',
                template: '<div>{{ legacyLabel }}</div>',
                computed: {
                    legacyLabel(): string {
                        oldFunction();

                        return 'legacy';
                    },
                },
            };

            mount({
                name: 'sw-author',
                components: { 'sw-caller': caller },
                template: '<sw-caller />',
            });

            expect(recordedSites()).toEqual(['sw-caller']);
        });

        it('names the same site for a component calling a deprecated function, whichever parent renders it', () => {
            const caller = { name: 'sw-caller', template: '<div />', created: oldFunction };

            mount({ name: 'sw-list', components: { 'sw-caller': caller }, template: '<sw-caller />' });
            mount({ name: 'sw-grid', components: { 'sw-caller': caller }, template: '<sw-caller />' });

            expect(recordedSites()).toEqual(['sw-caller', 'sw-caller']);
        });

        it('tells two children of the same parent apart', () => {
            mount({
                name: 'sw-owner',
                components: {
                    'sw-first-caller': { name: 'sw-first-caller', template: '<div />', created: oldFunction },
                    'sw-second-caller': { name: 'sw-second-caller', template: '<div />', created: oldFunction },
                },
                template: '<sw-first-caller /><sw-second-caller />',
            });

            expect(recordedSites()).toEqual([
                'sw-first-caller',
                'sw-second-caller',
            ]);
        });

        it('names the parent for a deprecated method it calls on a child through a template ref', () => {
            mount({
                name: 'sw-parent',
                components: { 'sw-child': { name: 'sw-child', template: '<div />', methods: { oldMethod: oldFunction } } },
                template: '<sw-child ref="child" />',
                mounted(this: ComponentPublicInstance) {
                    (this.$refs.child as { oldMethod: () => void }).oldMethod();
                },
            });

            expect(recordedSites()).toEqual(['sw-parent']);
        });
    });

    describe('outside a component', () => {
        it('names the spec when it calls the deprecated function or the guard itself', () => {
            oldFunction();
            Shopware.Feature.triggerDeprecationOrThrow('V7_0_0_0', 'inlineGuard() is deprecated.');

            expect(recordedSites()).toEqual([spec, spec]);
        });

        it('names the caller of a deprecated function that guards through a helper of its module', () => {
            oldWrappedFunction();

            expect(recordedSites()).toEqual([spec]);
        });

        it('names whoever started the chain for a component calling its own deprecated method after an await', async () => {
            mount(selfDeprecatingComponent);
            await flushPromises();

            expect(recordedSites()).toEqual([spec]);
        });

        it('names the file of a module that calls the deprecated function', () => {
            oldFunctionCaller();

            expect(recordedSites()).toEqual(['test/_helper_/_fixtures/deprecations/oldFunctionCaller.ts']);
        });
    });

    describe('callerFile', () => {
        it('finds the caller on the stack, skipping the guard, dependencies and $super', () => {
            const admin = process.env.ADMIN_PATH;
            const stack = [
                'Error',
                `    at recordDeprecation (${admin}/test/_helper_/deprecations.ts:83:18)`,
                `    at oldFunction (${admin}/src/core/old.ts:12:5)`,
                `    at Proxy.$super (${admin}/src/core/factory/async-component.factory.ts:40:9)`,
                `    at callWithErrorHandling (${admin}/node_modules/vue/index.js:200:19)`,
                '    at process.processTicksAndRejections (node:internal/process/task_queues:95:5)',
                `    at ${admin}/src/app/caller.ts:7:3`,
            ].join('\n');

            expect(callerFile(stack)).toBe('src/app/caller.ts');
        });

        it('finds no caller when only the deprecated module is on the stack', () => {
            expect(callerFile(`Error\n    at oldFunction (${process.env.ADMIN_PATH}/src/core/old.ts:12:5)`)).toBeUndefined();
        });
    });

    describe('ownFrames', () => {
        it('skips the deprecation plugin, so the frames start at the usage', () => {
            const admin = process.env.ADMIN_PATH;
            const stack = [
                'Error',
                `    at guard (${admin}/src/app/plugin/deprecation.plugin.ts:70:30)`,
                `    at ${admin}/test/app/legacy.spec.ts:7:3`,
            ].join('\n');

            expect(ownFrames(stack).map(location)).toEqual(['test/app/legacy.spec.ts:7:3']);
        });
    });

    describe('location', () => {
        const file = `${process.env.ADMIN_PATH}/test/a folder/oldFunction.ts`;

        it('reads a frame with a function name and a path with spaces', () => {
            expect(ownFrames(`    at oldFunction (${file}:13:13)`).map(location)).toEqual([
                'test/a folder/oldFunction.ts:13:13',
            ]);
        });

        it('reads a frame without a function name and a path with spaces', () => {
            expect(ownFrames(`    at ${file}:13:13`).map(location)).toEqual(['test/a folder/oldFunction.ts:13:13']);
        });
    });
});
