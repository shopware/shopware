import type { CallExpression, ObjectLiteralExpression } from 'ts-morph';
import type { MigrationStatus } from '../types';

export interface TransformScriptResult {
    script: string;
    scriptType: 'setup' | 'options';
    status: MigrationStatus;
    blockers: string[];
    /** Names exposed in the `public:` return of createExtendableSetup. */
    publicNames: string[];
}

export interface DataProp {
    name: string;
    /** Exact source text of the value, e.g. `'Default Title'`, `0`, `false` */
    valueText: string;
}

export interface ExtractDataPropsResult {
    dataProps: DataProp[];
    unsupportedEntries: string[];
}

export interface InjectProp {
    localName: string;
    sourceKey: string;
    defaultValueText?: string;
    treatDefaultAsFactory?: boolean;
}

export interface ExtractInjectPropsResult {
    injectProps: InjectProp[];
    unsupportedEntries: string[];
}

export interface ComponentRegistration {
    call: CallExpression;
    isExtend: boolean;
    componentName: string;
    optionsObject: ObjectLiteralExpression | undefined;
    parentComponentName: string | null;
}

export type ComputedProp =
    | { name: string; kind: 'getter'; bodyText: string }
    | { name: string; kind: 'getter-setter'; getterBodyText: string; setterParam: string; setterBodyText: string };

export interface ExtractComputedPropsResult {
    computedProps: ComputedProp[];
    unsupportedEntries: string[];
}

export interface WatchProp {
    name: string;
    paramsText: string;
    bodyText?: string;
    handlerName?: string;
    isAsync?: boolean;
    deep?: boolean;
    immediate?: boolean;
}

export interface ExtractWatchPropsResult {
    watchProps: WatchProp[];
    unsupportedEntries: string[];
}

export interface EmitsDefinition {
    keys: string[];
    objectText: string | null;
}

export interface MethodProp {
    name: string;
    /** Full parameter list text including types and defaults */
    paramsText: string;
    bodyText: string;
    isAsync: boolean;
    /**
     * When set, the method is emitted verbatim as `const name = rawText;` after
     * `this.` rewriting — used for property-assignment methods like `debounce(...)`.
     */
    rawText?: string;
}

export interface ExtractMethodPropsResult {
    methodProps: MethodProp[];
    unsupportedEntries: string[];
}

export interface LifecycleHook {
    hookName: string;
    /** null means "run directly in setup" (i.e. created) */
    compositionName: string | null;
    bodyText: string;
    isAsync: boolean;
}

export type RewriteSnippetKind = 'body' | 'expression';

export interface CodeSnippet {
    text: string;
    kind: RewriteSnippetKind;
}

export interface RewriteContext {
    propNames: Set<string>;
    dataNames: Set<string>;
    computedNames: Set<string>;
    methodNames: Set<string>;
    /** inject() keys — accessed as plain identifiers in Composition API */
    injectNames: Set<string>;
}

export interface UsedComposables {
    needsRouter: boolean;
    needsRoute: boolean;
    needsNextTick: boolean;
    needsSlots: boolean;
    needsI18n: boolean;
    needsEmit: boolean;
    needsAttrs: boolean;
}

export interface UnsupportedInjectAnalysis {
    reasons: string[];
}
