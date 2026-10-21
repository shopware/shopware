/**
 * @sw-package framework
 */

export const SNAPSHOT_FORMAT_VERSION = 1;

/** Caches key on it; bump whenever the extraction output changes. */
export const EXTRACTOR_VERSION = '4';

/** The `ref` of a snapshot taken from the working tree, uncommitted changes included. */
export const WORKTREE_REF = 'WORKTREE';

export type TypeSource = 'typescript' | 'jsdoc' | 'inferred' | 'unknown';

export type Visibility = 'public' | 'private' | 'internal' | 'experimental';

/**
 * `@deprecated tag:v6.8.0 - msg` becomes `{ version: 'v6.8.0', message: 'msg' }`;
 * the component option `deprecated: { version, comment }` maps the same way.
 */
export interface Deprecation {
    version: string | null;
    message: string;
}

/** `file` is relative to the admin root with '/' separators, `line` is 1-based. */
export interface SourceLocation {
    file: string;
    line: number;
}

export interface ApiMeta {
    visibility: Visibility;
    deprecation: Deprecation | null;
    location: SourceLocation | null;
}

/** `file` follows the `SourceLocation` convention, null when the problem is not tied to a file. */
export interface SnapshotDiagnostic {
    file: string | null;
    message: string;
}

export interface ExtractionResult<T> {
    result: T;
    diagnostics: SnapshotDiagnostic[];
}

export interface ApiSnapshot {
    formatVersion: number;
    extractorVersion: string;
    /** `WORKTREE_REF` or the ref as given, e.g. 'trunk' or 'v6.6.10.29'. */
    ref: string;
    /** Resolved sha, null for the working tree. */
    commit: string | null;
    /** ISO 8601 */
    createdAt: string;
    shopwareObject: ShopwareObjectNode;
    /** One entry per registered component name, sorted by name. */
    components: ComponentEntry[];
    diagnostics: SnapshotDiagnostic[];
}

export type ShopwareObjectNodeKind =
    | 'object'
    | 'function'
    | 'class'
    | 'method'
    | 'property'
    | 'getter'
    | 'constant'
    | 'instance';

export interface ShopwareObjectNode extends ApiMeta {
    /**
     * Dot path from the root, e.g. 'Shopware' or 'Shopware.Component.register'.
     * Instance members use '#': 'Shopware.Data.ChangesetGenerator#generate'.
     */
    path: string;
    name: string;
    kind: ShopwareObjectNodeKind;
    /**
     * Display text: a short summary for containers (objects, classes, instances), the full type text
     * for leaves, one line per overload. Compare `callSignatures` for callables.
     */
    signature: string;
    /** One entry per overload, empty for non-callables. */
    callSignatures: CallSignatureEntry[];
    typeSource: TypeSource;
    /** A depth or size limit stopped the walk at this node. */
    truncated: boolean;
    children: ShopwareObjectNode[];
}

export interface CallSignatureEntry {
    parameters: ParameterEntry[];
    returnType: string;
}

/** Core `Component.override(...)` configs are merged into the entry they override, see `overriddenBy`. */
export type RegistrationKind = 'register' | 'extend';

export type ComponentLanguage = 'js' | 'ts' | 'vue';

/**
 * Members inherited through `extends` or mixins are not flattened into the entry: it lists only what
 * its own config, its merged overrides and its template declare; `extends` and `mixins` name the sources.
 */
export interface ComponentEntry extends ApiMeta {
    /** Registered name, e.g. 'sw-product-detail'; unique per snapshot and the match key between snapshots. */
    name: string;
    registration: RegistrationKind;
    /** Source files of the core overrides merged into this entry, sorted. */
    overriddenBy: string[];
    /** Parent name for `Component.extend`. */
    extends: string | null;
    /** From the `@sw-package` docblock tag. */
    packageName: string | null;
    language: ComponentLanguage;
    sourceFile: string;
    templateFile: string | null;
    props: PropEntry[];
    emits: NamedEntry[];
    /** From `<slot name="...">` in the template; an unnamed slot is 'default'. */
    slots: NamedEntry[];
    data: TypedEntry[];
    computed: ComputedEntry[];
    methods: MethodEntry[];
    inject: NamedEntry[];
    mixins: NamedEntry[];
    /** Document order. */
    blocks: BlockEntry[];
}

export interface NamedEntry extends ApiMeta {
    name: string;
}

export interface TypedEntry extends NamedEntry {
    type: string;
    typeSource: TypeSource;
}

export interface ComputedEntry extends TypedEntry {
    writable: boolean;
}

/** `type` as declared ('String', 'String | Number'), `default` as normalized source text. */
export interface PropEntry extends NamedEntry {
    type: string | null;
    required: boolean;
    default: string | null;
}

export interface ParameterEntry {
    name: string;
    type: string;
    optional: boolean;
    rest: boolean;
}

export interface MethodEntry extends NamedEntry {
    parameters: ParameterEntry[];
    returnType: string;
    async: boolean;
    /** Covers the parameters and the return type. */
    typeSource: TypeSource;
}

/** `native` is a `<sw-block>` in a `.vue` file. */
export type BlockKind = 'twig' | 'native';

/**
 * The diff matches blocks of a component by `name` when that name occurs exactly once in both
 * snapshots, otherwise by `key`.
 */
export interface BlockEntry extends NamedEntry {
    /**
     * Block names from the root block down to this one, joined with '/', e.g. 'sw_page/sw_page_content'.
     * The n-th occurrence (n >= 2) of the same path in the template gets a `~n` suffix: 'sw_page/sw_page_content~2'.
     */
    key: string;
    kind: BlockKind;
    /** Name of the enclosing block. */
    parent: string | null;
    /** 0 for top-level blocks. */
    depth: number;
    /** The block's own markup, dedented, each nested block replaced by `nestedBlockPlaceholder(name)`. */
    content: string;
    /** sha1 hex of `content` with all whitespace runs collapsed to one space and trimmed. */
    contentHash: string;
}

export function nestedBlockPlaceholder(name: string): string {
    return `{% block ${name} %}{% endblock %}`;
}
