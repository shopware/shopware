/**
 * @sw-package framework
 */

/**
 * Runtime oracle for the codemod. The original and the generated component are written to a temp
 * directory and imported through Jest, so the `.vue` runs through the same setup transform and
 * vue-jest as any Administration spec, against the real runtime and the global `sw-block`. Both
 * render an observer template unless `useConvertedTemplate` is set, so a block difference cannot
 * hide a script mismatch.
 *
 * Outside the Administration package, bare imports resolve through a linked `node_modules`, and
 * fixtures convert as TypeScript: vue-jest finds no Babel config there but uses the project tsconfig.
 */

import * as fs from 'fs';
import * as path from 'path';

import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, type Component, type Plugin } from 'vue';
import type { ConvertResult } from './convert-component';
import type { RuntimeFixture } from './runtime-equivalence-fixtures';
import { convertSource, makeRoot, writeFile } from './spec-helpers';

type RuntimeMountOptions = {
    props?: Record<string, unknown>;
    provide?: Record<string, unknown>;
    plugins?: Plugin[];
    errorHandler?: (error: unknown) => void;
    useConvertedTemplate?: boolean;
};

const OBSERVER_TEMPLATE = '<div />';

function replaceTemplate(sfc: string, template: string): string {
    const start = sfc.indexOf('<template>');
    const end = sfc.lastIndexOf('</template>');

    if (start < 0 || end < 0) {
        throw new Error('Generated SFC has no replaceable template block');
    }

    return `${sfc.slice(0, start)}<template>${template}</template>${sfc.slice(end + '</template>'.length)}`;
}

const ADMIN_NODE_MODULES = path.resolve(__dirname, '../../../node_modules');

function makeModuleRoot(prefix: string): string {
    const root = makeRoot(prefix);

    fs.symlinkSync(ADMIN_NODE_MODULES, path.join(root, 'node_modules'), 'dir');

    return root;
}

async function importDefault<T>(file: string): Promise<T> {
    return ((await import(file)) as { default: T }).default;
}

/** The fixture source carries no Twig import of its own, so the conversion copy gets one. */
async function convertFixture(fixture: RuntimeFixture): Promise<ConvertResult> {
    return convertSource(fixture.name, `import template from './${fixture.name}.html.twig';\n${fixture.jsSource}`, {
        lang: 'ts',
        twigSource: fixture.twigSource,
    });
}

async function loadOriginal(fixture: RuntimeFixture): Promise<Component> {
    const file = writeFile(makeModuleRoot('sfc-migration-original-'), 'index.js', fixture.jsSource);
    const options = await importDefault<Record<string, unknown>>(file);

    return defineComponent({ ...options, template: OBSERVER_TEMPLATE });
}

async function loadGenerated(
    fixture: RuntimeFixture,
    result: ConvertResult,
    useConvertedTemplate = false,
): Promise<Component> {
    if (!result.sfc) {
        throw new Error(`Cannot load ${fixture.name}: the conversion produced no SFC`);
    }

    const dir = makeModuleRoot('sfc-migration-generated-');

    if (result.module) {
        writeFile(dir, result.module.fileName, result.module.source);
    }

    const sfc = useConvertedTemplate ? result.sfc : replaceTemplate(result.sfc, OBSERVER_TEMPLATE);

    return importDefault<Component>(writeFile(dir, `${fixture.name}.vue`, sfc));
}

function mountComponent(component: Component, options: RuntimeMountOptions = {}): VueWrapper {
    return mount(component, {
        props: options.props,
        global: {
            plugins: options.plugins ?? [],
            provide: options.provide,
            config: options.errorHandler ? { errorHandler: options.errorHandler } : {},
        },
    } as never) as VueWrapper;
}

/** `[original, generated]`, mounted with the same options. */
async function mountBoth(
    fixture: RuntimeFixture,
    result: ConvertResult,
    options: RuntimeMountOptions = {},
): Promise<[VueWrapper, VueWrapper]> {
    const original = mountComponent(await loadOriginal(fixture), { ...options, useConvertedTemplate: false });
    const generated = mountComponent(await loadGenerated(fixture, result, options.useConvertedTemplate), options);

    await flushPromises();

    return [original, generated];
}

function setProbe(): unknown[] {
    const events: unknown[] = [];

    (globalThis as typeof globalThis & { __runtimeEquivalenceProbe?: unknown[] }).__runtimeEquivalenceProbe = events;

    return events;
}

export {
    type RuntimeMountOptions,
    convertFixture,
    flushPromises,
    loadGenerated,
    loadOriginal,
    mountBoth,
    mountComponent,
    setProbe,
};
