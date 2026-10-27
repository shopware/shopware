/**
 * @sw-package framework
 */

import type {
    ApiSnapshot,
    BlockEntry,
    CallSignatureEntry,
    ComponentEntry,
    ComputedEntry,
    MethodEntry,
    NamedEntry,
    ParameterEntry,
    PropEntry,
    ShopwareObjectNode,
    TypedEntry,
} from '../../snapshot/types';
import { createDiff } from '../create-diff';
import type { ApiChange } from '../types';

const meta = { visibility: 'public', deprecation: null, location: null } as const;

export function named(name: string, overrides: Partial<NamedEntry> = {}): NamedEntry {
    return { ...meta, name, ...overrides };
}

export function prop(name: string, overrides: Partial<PropEntry> = {}): PropEntry {
    return { ...meta, name, type: 'String', required: false, default: null, ...overrides };
}

export function typed(name: string, type: string, overrides: Partial<TypedEntry> = {}): TypedEntry {
    return { ...meta, name, type, typeSource: 'typescript', ...overrides };
}

export function computed(name: string, overrides: Partial<ComputedEntry> = {}): ComputedEntry {
    return { ...meta, name, type: 'string', typeSource: 'typescript', writable: false, ...overrides };
}

export function param(name: string, overrides: Partial<ParameterEntry> = {}): ParameterEntry {
    return { name, type: 'string', optional: false, rest: false, ...overrides };
}

export function method(name: string, parameters: ParameterEntry[] = [], overrides: Partial<MethodEntry> = {}): MethodEntry {
    return { ...meta, name, parameters, returnType: 'void', async: false, typeSource: 'typescript', ...overrides };
}

export function block(name: string, overrides: Partial<BlockEntry> = {}): BlockEntry {
    const content = overrides.content ?? `<div>${name}</div>`;

    return {
        ...meta,
        name,
        key: name,
        kind: 'twig',
        parent: null,
        depth: 0,
        content,
        contentHash: `hash:${content}`,
        ...overrides,
    };
}

export function component(name: string, overrides: Partial<ComponentEntry> = {}): ComponentEntry {
    return {
        ...meta,
        name,
        registration: 'register',
        overriddenBy: [],
        extends: null,
        packageName: 'framework',
        language: 'ts',
        sourceFile: `src/app/component/${name}/index.ts`,
        templateFile: null,
        props: [],
        emits: [],
        slots: [],
        data: [],
        computed: [],
        methods: [],
        inject: [],
        mixins: [],
        blocks: [],
        location: { file: `src/app/component/${name}/index.ts`, line: 1 },
        ...overrides,
    };
}

export function signature(parameters: ParameterEntry[] = [], returnType = 'void'): CallSignatureEntry {
    return { parameters, returnType };
}

export function node(path: string, overrides: Partial<ShopwareObjectNode> = {}): ShopwareObjectNode {
    return {
        ...meta,
        path,
        name: path.split(/[.#]/).pop() ?? path,
        kind: 'constant',
        signature: 'string',
        callSignatures: [],
        typeSource: 'typescript',
        truncated: false,
        children: [],
        ...overrides,
    };
}

export function root(children: ShopwareObjectNode[]): ShopwareObjectNode {
    return node('Shopware', { kind: 'object', signature: 'object', children });
}

export function snapshot(overrides: Partial<ApiSnapshot> = {}): ApiSnapshot {
    return {
        formatVersion: 1,
        extractorVersion: '1',
        ref: 'WORKTREE',
        commit: null,
        createdAt: '2026-10-21T00:00:00.000Z',
        shopwareObject: root([node('Shopware.version')]),
        components: [component('sw-unchanged')],
        diagnostics: [],
        ...overrides,
    };
}

/** An untouched component stays on both sides, so the partial-snapshot guard does not kick in. */
export function diffComponents(base: ComponentEntry[], head: ComponentEntry[]): ApiChange[] {
    return createDiff(
        snapshot({ components: [component('sw-unchanged'), ...base] }),
        snapshot({ components: [component('sw-unchanged'), ...head] }),
    ).changes;
}

export function diffComponent(base: Partial<ComponentEntry>, head: Partial<ComponentEntry>): ApiChange[] {
    return diffComponents([component('sw-foo', base)], [component('sw-foo', head)]);
}

/** An untouched node stays on both sides, so the partial-snapshot guard does not kick in. */
export function diffObject(base: ShopwareObjectNode[], head: ShopwareObjectNode[]): ApiChange[] {
    return createDiff(
        snapshot({ shopwareObject: root([node('Shopware.version'), ...base]) }),
        snapshot({ shopwareObject: root([node('Shopware.version'), ...head]) }),
    ).changes;
}

export function summarize(changes: ApiChange[]): Array<Pick<ApiChange, 'kind' | 'member' | 'change' | 'severity'>> {
    return changes.map(({ kind, member, change, severity }) => ({ kind, member, change, severity }));
}
