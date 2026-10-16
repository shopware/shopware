/**
 * @sw-package framework
 */

import { mount, flushPromises } from '@vue/test-utils';
import { defineAsyncComponent } from 'vue';
import { allowDeprecation, deprecations } from './deprecations';
import { callerFile } from './deprecationUsageSite';
import oldFunction, { oldWrappedFunction } from './_fixtures/deprecations/oldFunction';
import oldFunctionCaller from './_fixtures/deprecations/oldFunctionCaller';
import selfDeprecatingComponent from './_fixtures/deprecations/selfDeprecatingComponent';

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
 * mountDeprecatedComponentInSlot();
 */
function mountDeprecatedComponentInSlot(): void {
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
            mountDeprecatedComponentInSlot();

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

        it('names the component and its author for a deprecated member the component reads itself', () => {
            const selfDeprecating = {
                name: 'sw-self-deprecating',
                template: '<div>{{ legacyLabel }}</div>',
                computed: {
                    legacyLabel(): string {
                        oldFunction();

                        return 'legacy';
                    },
                },
            };

            mount({
                name: 'sw-host',
                components: { 'sw-self-deprecating': selfDeprecating },
                template: '<sw-self-deprecating />',
            });

            expect(recordedSites()).toEqual(['sw-host > sw-self-deprecating']);
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

            expect(recordedSites()).toEqual(['sw-owner > sw-first-caller', 'sw-owner > sw-second-caller']);
        });
    });

    describe('outside a component', () => {
        it('names the spec when it calls the deprecated function or the guard itself', () => {
            oldFunction();
            Shopware.Feature.triggerDeprecationOrThrow('V7_0_0_0', 'inlineGuard() is deprecated.');

            expect(recordedSites()).toEqual([spec, spec]);
        });

        it('names the file of a module that calls the deprecated function', () => {
            oldFunctionCaller();

            expect(recordedSites()).toEqual(['test/_helper_/_fixtures/deprecations/oldFunctionCaller.ts']);
        });

        it('names the caller of a deprecated function that guards through a helper of its module', () => {
            oldWrappedFunction();

            expect(recordedSites()).toEqual([spec]);
        });

        it('names whoever started the chain for a component calling its own deprecated method after an await', async () => {
            await mount(selfDeprecatingComponent).vm.load();

            expect(recordedSites()).toEqual([spec]);
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
});
