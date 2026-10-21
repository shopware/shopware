/**
 * @sw-package framework
 */

import { SEVERITIES, type ApiChange, type ApiDiff, type Severity } from '../../diff/types';
import type {
    ApiSnapshot,
    BlockEntry,
    ComponentEntry,
    ShopwareObjectNode,
    ShopwareObjectNodeKind,
    Visibility,
} from '../../snapshot/types';
import type { CompareResponse } from '../api';
import { changeImpact, PUBLIC_SEVERITIES, type PrivateChangeFields } from '../model/severity';

const META = { visibility: 'public' as Visibility, deprecation: null, location: null };

export function objectNode(
    path: string,
    kind: ShopwareObjectNodeKind,
    children: ShopwareObjectNode[] = [],
    overrides: Partial<ShopwareObjectNode> = {},
): ShopwareObjectNode {
    return {
        ...META,
        path,
        name: path.split(/[.#]/).pop() ?? path,
        kind,
        signature: kind === 'function' || kind === 'method' ? '() => void' : '{ … }',
        callSignatures: [],
        typeSource: 'typescript',
        truncated: false,
        children,
        ...overrides,
    };
}

/** `path` is the chain of block names from the root, e.g. 'page/page_actions'. */
export function block(path: string, content = ''): BlockEntry {
    const names = path.split('/');

    return {
        ...META,
        name: names[names.length - 1],
        key: path,
        kind: 'twig',
        parent: names.length > 1 ? names[names.length - 2] : null,
        depth: names.length - 1,
        content,
        contentHash: content,
    };
}

export function component(name: string, overrides: Partial<ComponentEntry> = {}): ComponentEntry {
    return {
        ...META,
        name,
        registration: 'register',
        overriddenBy: [],
        extends: null,
        packageName: 'framework',
        language: 'ts',
        sourceFile: `src/app/component/${name}/index.ts`,
        templateFile: `src/app/component/${name}/${name}.html.twig`,
        props: [],
        emits: [],
        slots: [],
        data: [],
        computed: [],
        methods: [],
        inject: [],
        mixins: [],
        blocks: [],
        ...overrides,
    };
}

export function snapshot(ref: string, shopwareObject: ShopwareObjectNode, components: ComponentEntry[]): ApiSnapshot {
    return {
        formatVersion: 1,
        extractorVersion: '1',
        ref,
        commit: ref === 'WORKTREE' ? null : '84182fe36a97aef4f1f417bb2e46109640b032f8',
        createdAt: '2026-10-21T12:00:00.000Z',
        shopwareObject,
        components,
        diagnostics: [],
    };
}

export function change(
    severity: Severity,
    kind: ApiChange['kind'],
    owner: string,
    member: string,
    overrides: Partial<ApiChange & PrivateChangeFields> = {},
): ApiChange {
    const area = kind === 'objectMember' ? 'shopwareObject' : 'component';

    return {
        id: `${area}:${owner}:${kind}:${member}`,
        area,
        kind,
        change: 'changed',
        severity,
        owner,
        member,
        before: null,
        after: null,
        reasons: [`${member} changed.`],
        location: null,
        impact: null,
        privateVisibility: severity === 'private' ? 'private' : null,
        ...overrides,
    } as ApiChange;
}

export function compareResponse(base: ApiSnapshot, head: ApiSnapshot, changes: ApiChange[]): CompareResponse {
    const bySeverity = Object.fromEntries(
        SEVERITIES.map((severity) => [
            severity,
            changes.filter((entry) => entry.severity === severity).length,
        ]),
    ) as Record<Severity, number>;
    const ref = (side: ApiSnapshot): ApiDiff['base'] => ({
        ref: side.ref,
        commit: side.commit,
        createdAt: side.createdAt,
        extractorVersion: side.extractorVersion,
        diagnostics: side.diagnostics,
    });

    return {
        base,
        head,
        diff: {
            base: ref(base),
            head: ref(head),
            summary: {
                total: changes.length,
                bySeverity,
                privateBySeverity: Object.fromEntries(
                    PUBLIC_SEVERITIES.map((severity) => [
                        severity,
                        changes.filter((entry) => entry.severity === 'private' && changeImpact(entry) === severity).length,
                    ]),
                ),
                hasBreaking: bySeverity.breaking > 0,
            } as ApiDiff['summary'],
            changes,
        },
        timings: { baseMs: 10, headMs: 20, diffMs: 1 },
    };
}

/** Base: trunk, head: the working tree. A block moves, one is removed, the object tree has a private branch. */
export function productDetailFixture(): CompareResponse {
    const base = snapshot(
        'trunk',
        objectNode('Shopware', 'object', [
            objectNode('Shopware.Component', 'object', [objectNode('Shopware.Component.register', 'function')]),
            objectNode('Shopware.Internal', 'object', [objectNode('Shopware.Internal.secret', 'function')], {
                visibility: 'private',
            }),
        ]),
        [
            component('sw-product-detail', {
                methods: [
                    { ...META, name: 'onSave', parameters: [], returnType: 'void', async: false, typeSource: 'typescript' },
                ],
                blocks: [
                    block('sw_product_detail', '<sw-page>{% block sw_product_detail_actions %}{% endblock %}</sw-page>'),
                    block('sw_product_detail/sw_product_detail_actions', '<div>'),
                    block('sw_product_detail/sw_product_detail_actions/sw_product_detail_actions_menu', '<sw-menu>'),
                    block(
                        'sw_product_detail/sw_product_detail_actions/sw_product_detail_actions_menu/sw_product_detail_actions_duplicate',
                        '<button>Duplicate</button>',
                    ),
                ],
            }),
            component('sw-button-process', { visibility: 'private' }),
        ],
    );
    const head = snapshot(
        'WORKTREE',
        objectNode('Shopware', 'object', [
            objectNode('Shopware.Component', 'object', [
                objectNode('Shopware.Component.register', 'function', [], { signature: '(name: string) => void' }),
                objectNode('Shopware.Component.build', 'function'),
            ]),
            objectNode('Shopware.Internal', 'object', [objectNode('Shopware.Internal.secret', 'function')], {
                visibility: 'private',
            }),
        ]),
        [
            component('sw-product-detail', {
                blocks: [
                    block('sw_product_detail', '<sw-page>{% block sw_product_detail_actions %}{% endblock %}</sw-page>'),
                    block('sw_product_detail/sw_product_detail_actions', '<div>'),
                    block(
                        'sw_product_detail/sw_product_detail_actions/sw_product_detail_actions_duplicate',
                        '<button>\n    Duplicate\n</button>',
                    ),
                ],
            }),
            component('sw-button-process', { visibility: 'private' }),
        ],
    );

    return compareResponse(base, head, [
        change('breaking', 'method', 'sw-product-detail', 'onSave', {
            change: 'removed',
            before: '() => void',
            reasons: ['Removed without prior deprecation.'],
        }),
        // A block that exists on one side only is matched by its key.
        change(
            'breaking',
            'block',
            'sw-product-detail',
            'sw_product_detail/sw_product_detail_actions/sw_product_detail_actions_menu',
            {
                change: 'removed',
                reasons: ['Block removed.'],
            },
        ),
        change('potentiallyBreaking', 'block', 'sw-product-detail', 'sw_product_detail_actions_duplicate', {
            change: 'moved',
            reasons: ['Moved from `sw_product_detail_actions_menu` to `sw_product_detail_actions`.'],
        }),
        change('potentiallyBreaking', 'objectMember', 'Shopware.Component', 'Shopware.Component.register', {
            before: '() => void',
            after: '(name: string) => void',
        }),
        change('added', 'objectMember', 'Shopware.Component', 'Shopware.Component.build', { change: 'added' }),
        change('private', 'objectMember', 'Shopware.Internal', 'Shopware.Internal.secret', { impact: 'breaking' }),
        change('private', 'prop', 'sw-button-process', 'animationTimeout', {
            impact: 'compatible',
            privateVisibility: 'internal',
        }),
    ]);
}
