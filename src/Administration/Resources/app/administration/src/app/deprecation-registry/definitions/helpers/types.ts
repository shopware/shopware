/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * @sw-package framework
 */

export type FixLevel = 'auto' | 'unsafe-auto' | 'manual';

export type DeprecationReference = {
    type: string;
    target: string;
};

export type MigrationTransformContext = {
    phase?: 'metadata' | 'fix';
    valueKind?: 'static' | 'expression' | 'object-v-bind' | 'unknown';
    hasObjectVBind?: boolean;
};

export type MigrationTransformResult = {
    kind: string;
    fix: FixLevel;
    message?: string;
    [key: string]: unknown;
};

export type MigrationTransform = (context?: MigrationTransformContext) => MigrationTransformResult;

export type RuntimeUsageApi = {
    usedProps: Record<string, unknown>;
    componentName?: string;
};

export type ComponentUsageRuleApi = {
    context: {
        options: string[];
        sourceCode: {
            getText(node: unknown): string;
            text: string;
            ast?: {
                templateBody?: {
                    comments?: Array<{
                        value: string;
                        loc: {
                            start: { line: number };
                            end: { line: number };
                        };
                    }>;
                };
            };
        };
    };
    sourceCode: ComponentUsageRuleApi['context']['sourceCode'];
    node: Record<string, any>;
    migration: DeprecationMigration;
    usage: DeprecationUsage;
    appendRegistryContext(message: string, migration: DeprecationMigration): string;
    reportWithDuplicateReplacementGuard(descriptor: Record<string, any>): void;
    isFixDisabled(): boolean;
    getTransformResult(
        usage: DeprecationUsage,
        node: Record<string, any>,
        attribute: Record<string, any> | null,
    ): MigrationTransformResult | null;
    ast: {
        findMatchingPropAttribute(node: Record<string, any>, propName: string): Record<string, any> | undefined;
        hasMatchingPropAttribute(node: Record<string, any>, propName: string): boolean;
        findMatchingEventAttribute(node: Record<string, any>, eventName: string): Record<string, any> | undefined;
        findMatchingVModelAttribute(node: Record<string, any>, argumentName: string | null): Record<string, any> | undefined;
        findSlot(node: Record<string, any>, slotName: string): Record<string, any> | undefined;
        hasCodemodComment(node: Record<string, any>, text: string): boolean;
        getAttributeValueSource(attribute: Record<string, any>): string | null;
        getCondensedTextContent(node: Record<string, any>): string;
        getDirectiveName(attribute: Record<string, any>): string | null;
        getFirstElementChildWithoutSlot(node: Record<string, any>): Record<string, any> | undefined;
        getStaticAttributeName(attribute: Record<string, any>): string | null;
        getDirectiveArgumentName(attribute: Record<string, any>): string | null;
    };
};

export type DeprecationUsage = {
    kind?: string;
    fix?: FixLevel;
    message?: string;
    runtimeProp?: string;
    transform?: MigrationTransform;
    runtime?: {
        detect(api: RuntimeUsageApi): boolean;
    };
    eslint?: {
        report(api: ComponentUsageRuleApi): void;
    };
    [key: string]: unknown;
};

export type DeprecationMigration = {
    id: string;
    deprecatedIn: string;
    removedIn: string;
    description: string;
    references?: DeprecationReference[];
    usage: DeprecationUsage[];
    component?: string;
    replacement?: string;
    handler?: string;
    api?: string;
    files?: string[];
};

export type DeprecationDefinition = {
    componentApiMigrations?: DeprecationMigration[];
    globalApiMigrations?: DeprecationMigration[];
    jsApiMigrations?: DeprecationMigration[];
    assetMigrations?: DeprecationMigration[];
    templateBlockMigrations?: DeprecationMigration[];
    templateEventMigrations?: DeprecationMigration[];
    snippetKeyMigrations?: DeprecationMigration[];
    packageMigrations?: DeprecationMigration[];
};
