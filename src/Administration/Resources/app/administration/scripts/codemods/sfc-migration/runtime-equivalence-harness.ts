/**
 * @sw-package framework
 */

/**
 * Runtime oracle for the SFC migration codemod.
 *
 * The harness keeps all source in memory, runs the real convertComponent() and Shopware setup
 * transform, compiles the resulting script/template pair, and evaluates it as a Vue component.
 * An observer template is used for most cases so block registry behavior cannot hide a script
 * mismatch. Tests that need the generated block template can opt into the converted template.
 */

import { transformSync, type PluginItem } from '@babel/core';
import { parse as parseScript } from '@babel/parser';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import * as Vue from 'vue';
import { createRequire } from 'node:module';
import { Script, createContext } from 'node:vm';
import { attachOverrides, _overridesMap } from '../../../src/app/adapter/composition-extension-system';
import { transformShopwareSetupSfc } from '../../../build/vue-setup-transform/index.ts';
import usePublishedData from '../../../src/app/composables/use-published-data';
import { publishData } from '../../../src/core/service/extension-api-data.service';
import { convertComponent, type ConvertResult } from './convert-component';
import type { RuntimeFixture } from './runtime-equivalence-fixtures';

type RuntimeMountOptions = {
    props?: Record<string, unknown>;
    provide?: Record<string, unknown>;
    plugins?: Vue.Plugin[];
    useConvertedTemplate?: boolean;
};

type RuntimeProbe = {
    events: unknown[];
};

type RuntimeShopware = {
    Component: {
        attachOverrides: typeof attachOverrides;
    };
    ExtensionAPI: {
        publishData: typeof publishData;
    };
};

const runtimeShopware: RuntimeShopware = {
    Component: { attachOverrides },
    ExtensionAPI: { publishData },
};

/**
 * Modules a generated draft may import by their `src/…` alias. The evaluation boundary has no module
 * resolver, so anything the emitted code can import has to be handed to it here.
 */
const RUNTIME_MODULES: Record<string, unknown> = {
    'src/app/composables/use-published-data': { __esModule: true, default: usePublishedData },
};

const nodeRequire = createRequire(__filename);
const commonjs: PluginItem = (() => {
    const loaded: unknown = nodeRequire('@babel/plugin-transform-modules-commonjs');

    if (loaded && typeof loaded === 'object' && 'default' in loaded) {
        return loaded.default as PluginItem;
    }

    return loaded as PluginItem;
})();

const BlockDataScopePlugin: Vue.Plugin = {
    install(app) {
        const existing = Object.getOwnPropertyDescriptor(app.config.globalProperties, '$dataScope');

        if (existing && existing.configurable === false) {
            return;
        }

        Object.defineProperty(app.config.globalProperties, '$dataScope', {
            get: () => ({}),
            enumerable: true,
        });
    },
};

const TransparentBlock = Vue.defineComponent({
    props: {
        data: {
            type: Object,
            default: null,
        },
        name: {
            type: String,
            default: undefined,
        },
    },
    setup(props, context) {
        return () => context.slots.default?.(props.data) ?? [];
    },
});

function setProbe(events: unknown[] = []): RuntimeProbe {
    const probe = { events };

    (globalThis as typeof globalThis & { __runtimeEquivalenceProbe?: unknown }).__runtimeEquivalenceProbe = probe.events;

    return probe;
}

function resetOverrides(): void {
    Object.keys(_overridesMap).forEach((key) => {
        delete _overridesMap[key];
    });
}

/**
 * Both sides are mounted against this instead of the fixture's own markup, so a block-registry
 * difference cannot hide a script mismatch. Tests needing the generated markup pass
 * `useConvertedTemplate`.
 */
const OBSERVER_TEMPLATE = '<div />';

function replaceTemplate(sfc: string, observerTemplate: string): string {
    const templateStart = sfc.indexOf('<template>');
    const templateEnd = sfc.indexOf('</template>', templateStart);

    if (templateStart < 0 || templateEnd < 0) {
        throw new Error('Generated SFC has no replaceable template block');
    }

    return `${sfc.slice(0, templateStart)}<template>${observerTemplate}</template>${sfc.slice(templateEnd + '</template>'.length)}`;
}

function evaluateModule(source: string): Record<string, unknown> {
    const transformed = transformSync(source, {
        babelrc: false,
        configFile: false,
        plugins: [commonjs],
    });

    if (!transformed?.code) {
        throw new Error('Babel did not produce executable module code');
    }

    const moduleRecord: { exports: Record<string, unknown> } = { exports: {} };
    const requireModule = (id: string): unknown => {
        if (id === 'vue') {
            return Vue;
        }

        if (id in RUNTIME_MODULES) {
            return RUNTIME_MODULES[id];
        }

        return nodeRequire(id);
    };

    // The module is generated in memory and evaluated in a deliberately tiny CommonJS boundary.
    const context = createContext({
        exports: moduleRecord.exports,
        globalThis,
        module: moduleRecord,
        require: requireModule,
        Shopware: runtimeShopware,
    });
    const execute = new Script(`(function(require, module, exports, Shopware) {\n${transformed.code}\n})`).runInContext(
        context,
    ) as (requireModule: (id: string) => unknown, module: unknown, exports: unknown, shopware: unknown) => void;

    execute(requireModule, moduleRecord, moduleRecord.exports, runtimeShopware);

    return moduleRecord.exports;
}

function compileOptionsComponent(fixture: RuntimeFixture): Vue.Component {
    const module = evaluateModule(fixture.jsSource);
    const options = module.default;

    if (!options || typeof options !== 'object') {
        throw new Error(`Fixture ${fixture.name} did not export an Options API object`);
    }

    return Vue.defineComponent({
        ...(options as Record<string, unknown>),
        template: OBSERVER_TEMPLATE,
    });
}

function compileGeneratedComponent(sfc: string, filename: string, observerTemplate?: string): Vue.Component {
    const source = observerTemplate ? replaceTemplate(sfc, observerTemplate) : sfc;
    const lowered = transformShopwareSetupSfc(source, filename);

    if (!lowered) {
        throw new Error(`Generated SFC ${filename} was not recognized by the Shopware setup transform`);
    }

    const parsed = parse(lowered.code, { filename });
    const script = compileScript(parsed.descriptor, { id: filename });
    const template = compileTemplate({
        filename,
        id: filename,
        source: parsed.descriptor.template?.content ?? '',
    });

    if (template.errors.length > 0) {
        throw new Error(String(template.errors[0]));
    }

    const sourceModule = [
        script.content,
        template.code,
        'const __runtimeSfc = module.exports.default;',
        'if (!__runtimeSfc) { throw new Error("Generated SFC has no default export"); }',
        ' __runtimeSfc.render = module.exports.render;',
        ' module.exports = __runtimeSfc;',
    ].join('\n');

    const module = evaluateModule(sourceModule);

    return module as unknown as Vue.Component;
}

function globalMountOptions(options: RuntimeMountOptions, useConvertedTemplate: boolean): Record<string, unknown> {
    const components = useConvertedTemplate ? { 'sw-block': TransparentBlock } : undefined;

    return {
        components,
        plugins: [
            BlockDataScopePlugin,
            ...(options.plugins ?? []),
        ],
        provide: options.provide,
    };
}

async function convertFixture(fixture: RuntimeFixture): Promise<ConvertResult> {
    // Runtime fixtures intentionally keep the original Options API source free of imports so the
    // in-memory Options API module can be evaluated directly. The conversion input still needs the
    // authoritative template-import range that a production source model supplies, so add a
    // synthetic import only to the conversion copy.
    const conversionSource = `import template from './${fixture.name}.html.twig';\n${fixture.jsSource}`;
    const templateImport = parseScript(conversionSource, { sourceType: 'module' }).program.body.find(
        (statement) => statement.type === 'ImportDeclaration' && statement.source.value.endsWith('.html.twig'),
    );

    if (!templateImport) {
        throw new Error(`Fixture ${fixture.name} has no Twig import`);
    }

    return convertComponent({
        componentName: fixture.name,
        jsSource: conversionSource,
        lang: 'js',
        twigSource: fixture.twigSource,
        vuePath: `${fixture.name}.vue`,
        templateImportRange: { start: templateImport.start as number, end: templateImport.end as number },
    });
}

function mountOriginal(fixture: RuntimeFixture, options: RuntimeMountOptions = {}): VueWrapper {
    return mountComponent(compileOptionsComponent(fixture), options, false);
}

function mountComponent(component: Vue.Component, options: RuntimeMountOptions, useConvertedTemplate: boolean): VueWrapper {
    return mount(component, {
        global: globalMountOptions(options, useConvertedTemplate),
        props: options.props,
    } as never) as VueWrapper;
}

function mountGenerated(fixture: RuntimeFixture, result: ConvertResult, options: RuntimeMountOptions = {}): VueWrapper {
    if (!result.sfc) {
        throw new Error(`Cannot mount ${fixture.name}: conversion produced no SFC`);
    }

    const component = compileGeneratedComponent(
        result.sfc,
        `${fixture.name}.vue`,
        options.useConvertedTemplate ? undefined : OBSERVER_TEMPLATE,
    );

    return mountComponent(component, options, options.useConvertedTemplate ?? false);
}

function mountOriginalPair(fixture: RuntimeFixture, options: RuntimeMountOptions = {}): [VueWrapper, VueWrapper] {
    const component = compileOptionsComponent(fixture);

    return [
        mountComponent(component, options, false),
        mountComponent(component, options, false),
    ];
}

function mountGeneratedPair(
    fixture: RuntimeFixture,
    result: ConvertResult,
    options: RuntimeMountOptions = {},
): [VueWrapper, VueWrapper] {
    if (!result.sfc) {
        throw new Error(`Cannot mount ${fixture.name}: conversion produced no SFC`);
    }

    const component = compileGeneratedComponent(
        result.sfc,
        `${fixture.name}.vue`,
        options.useConvertedTemplate ? undefined : OBSERVER_TEMPLATE,
    );

    return [
        mountComponent(component, options, options.useConvertedTemplate ?? false),
        mountComponent(component, options, options.useConvertedTemplate ?? false),
    ];
}

async function runEquivalentOrConservative(
    fixture: RuntimeFixture,
    exercise: (original: VueWrapper, generated: VueWrapper) => Promise<void> | void,
    options: RuntimeMountOptions = {},
): Promise<{ result: ConvertResult; conservative: boolean; original?: VueWrapper; generated?: VueWrapper }> {
    resetOverrides();
    const result = await convertFixture(fixture);

    if (result.outcome !== 'full') {
        return { result, conservative: true };
    }

    const original = mountOriginal(fixture, options);
    const generated = mountGenerated(fixture, result, options);

    await flushPromises();
    await exercise(original, generated);

    return { result, conservative: false, original, generated };
}

export {
    type RuntimeMountOptions,
    convertFixture,
    flushPromises,
    mountGenerated,
    mountGeneratedPair,
    mountOriginal,
    mountOriginalPair,
    resetOverrides,
    runEquivalentOrConservative,
    setProbe,
};
