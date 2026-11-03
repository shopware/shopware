/* eslint-disable @typescript-eslint/no-explicit-any -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

/** Fix levels from the most to the least automatic; see the deprecation coding guideline for their meaning. */
export const FIX_LEVELS = [
    'auto',
    'unsafe-auto',
    'manual',
] as const;

export type FixLevel = (typeof FIX_LEVELS)[number];

export type DeprecationReference = {
    type: 'upgrade';
    /** `UPGRADE-<version>.md#<heading-anchor>`, relative to the repository root. */
    target: string;
};

/** A vue-eslint-parser template node (element, attribute, directive key, …). */
export type TemplateNode = Record<string, any>;

export type Fix = {
    range: [number, number];
    text: string;
};

export type Fixer = {
    insertTextAfter(target: TemplateNode, text: string): Fix;
    insertTextAfterRange(range: [number, number], text: string): Fix;
    insertTextBefore(target: TemplateNode, text: string): Fix;
    insertTextBeforeRange(range: [number, number], text: string): Fix;
    remove(target: TemplateNode): Fix;
    replaceText(target: TemplateNode, text: string): Fix;
};

/** An attribute a fix writes, so the rule can drop the fix when the element already has that attribute. */
export type ProducedAttribute = {
    kind: 'prop' | 'event' | 'model';
    /** Prop and event names compare hyphen- and case-insensitively; models as `v-model` or `v-model:<argument>`. */
    name: string;
};

export type ComponentUsageReport = {
    node: TemplateNode;
    message: string;
    fix?: (fixer: Fixer) => Fix | Fix[] | Iterable<Fix> | null;
    produces?: ProducedAttribute;
};

/** What `no-deprecated-component-usage` hands to a step's `eslint.report` for every matching element. */
export type ComponentUsageRuleApi = {
    /** The matched element. */
    node: TemplateNode;
    migration: ComponentMigration;
    /** The step being run. */
    usage: ComponentUsageStep;
    sourceCode: {
        getText(node: unknown): string;
        text: string;
    };
    /** False when the rule runs with `disableFix` or the step (default: `usage`) is `fix: 'manual'`. */
    canAutoFix(usage?: { fix?: FixLevel }): boolean;
    /** Appends the registry context (description, removal version, references) to the headline. */
    buildMessage(text: string): string;
    report(descriptor: ComponentUsageReport): void;
    /** Inserts a codemod review comment with `text` before the target, on its own line with the target's indentation. */
    insertCodemodCommentBefore(fixer: Fixer, target: TemplateNode, text: string): Fix;
    ast: {
        findMatchingPropAttribute(node: TemplateNode, propName: string): TemplateNode | undefined;
        hasMatchingPropAttribute(node: TemplateNode, propName: string): boolean;
        findMatchingEventAttribute(node: TemplateNode, eventName: string): TemplateNode | undefined;
        findMatchingVModelAttribute(node: TemplateNode, argumentName: string | null): TemplateNode | undefined;
        findSlot(node: TemplateNode, slotName: string): TemplateNode | undefined;
        hasCodemodComment(node: TemplateNode, text: string): boolean;
        getAttributeValueSource(attribute: TemplateNode): string | null;
        getCondensedTextContent(node: TemplateNode): string;
        getDirectiveName(attribute: TemplateNode): string | null;
        getFirstElementChildWithoutSlot(node: TemplateNode): TemplateNode | undefined;
        getStaticAttributeName(attribute: TemplateNode): string | null;
        getDirectiveArgumentName(attribute: TemplateNode): string | null;
        /** The `v-bind="object"` attribute, which can hide or override any prop. */
        findObjectVBindAttribute(node: TemplateNode): TemplateNode | null | undefined;
        /** The `v-on="object"` attribute, which can hide or override any listener. */
        findObjectVOnAttribute(node: TemplateNode): TemplateNode | null | undefined;
    };
};

type UsageStepBase = {
    fix: FixLevel;
    /** Replaces the default "The "<api>" API is deprecated." headline of the report. */
    message?: string;
};

/** A step `no-deprecated-component-usage` runs on every element of the migration's replacement component. */
type ComponentUsageStepBase = UsageStepBase & {
    /** Runs the step only on elements the codemod just converted from the deprecated component. */
    onlyAfterConversion?: boolean;
    eslint: {
        report(api: ComponentUsageRuleApi): void;
    };
};

/**
 * Builder input of a usage step: its own fields, with `fix` optional because every builder defaults it.
 *
 * @example
 * const config: StepConfig<RenamePropStep> = { from: 'value', to: 'model-value' };
 */
export type StepConfig<Step extends UsageStepBase> = Omit<Step, 'kind' | 'fix' | 'eslint' | 'onlyAfterConversion'> & {
    fix?: FixLevel;
};

export type RenameComponentStep = UsageStepBase & { kind: 'rename-component'; from: string; to: string };

/** Hands the whole conversion to the ESLint rule `rule`, which `deprecation:migrate` enables. */
export type CodemodRuleStep = UsageStepBase & { kind: 'codemod-rule'; rule: string; from: string; to: string };

/**
 * Rewrites a removed prop into something else instead of only deleting it.
 *
 * @example
 * replaceProp({ prop: 'router-link', with: routerLinkClickHandler, fix: 'unsafe-auto' });
 */
export type PropReplacement = {
    /** Reported instead of a fix when an object `v-bind` can hide the prop. */
    objectVBindReason: string;
    produces?: ProducedAttribute;
    fix(api: ComponentUsageRuleApi, attribute: TemplateNode, fixer: Fixer): Fix | Fix[];
};

export type RenamePropStep = ComponentUsageStepBase & {
    kind: 'rename-prop';
    from: string;
    to: string;
};

export type InvertBooleanPropStep = ComponentUsageStepBase & {
    kind: 'invert-boolean-prop';
    from: string;
    to: string;
};

export type BooleanPropToValueStep = ComponentUsageStepBase & {
    kind: 'boolean-prop-to-value';
    from: string;
    to: string;
    /** The static value the replacement prop gets when the old boolean prop is set. */
    value: string;
};

export type RemovePropStep = ComponentUsageStepBase & {
    kind: 'remove-prop';
    prop: string;
};

export type ReplacePropStep = ComponentUsageStepBase & {
    kind: 'replace-prop';
    prop: string;
    with: PropReplacement;
};

export type MapPropValueStep = ComponentUsageStepBase & {
    kind: 'map-prop-value';
    prop: string;
    /** The static value that is deprecated. */
    from: string;
    /** The value that replaces it; unset for a value without replacement, which only a manual step can have. */
    to?: string;
    /** A boolean prop the replacement value needs, for example `ghost` when `variant="ghost"` becomes `variant="primary"`. */
    addBooleanProp?: string;
};

export type MapOptionsPropKeysStep = ComponentUsageStepBase & {
    kind: 'map-options-prop-keys';
    /** The prop that receives the option objects. */
    prop: string;
    /** Maps each deprecated option object key to its replacement key. */
    from: Record<string, string>;
    /** The step is skipped when the element already maps keys through one of these props. */
    unlessProps?: string[];
    /** Reported instead of a fix when the options expression cannot be rewritten automatically. */
    unsafeMessage?: string;
};

export type MissingPropStep = ComponentUsageStepBase & {
    kind: 'missing-prop';
    prop: string;
    /** The value source the fix writes into the added attribute. */
    value: string;
    /** Adds the prop as a `:prop` binding instead of a static attribute. */
    bind?: boolean;
    /** Where the prop goes in the start tag; defaults to `before-end`. */
    insertPosition?: 'after-name' | 'before-end';
    /** The step is skipped when the element already sets one of these props; defaults to `[prop]`. */
    unlessProps?: string[];
    /** Puts a codemod review comment above the element when the prop is added. */
    reviewHint?: string;
};

export type RemoveSlotStep = ComponentUsageStepBase & {
    kind: 'remove-slot';
    slot: string;
    /** Replaces the slot with `<!-- <replacementComment> -->` instead of a comment that quotes its content. */
    replacementComment?: string;
};

export type SlotToPropStep = ComponentUsageStepBase & {
    kind: 'slot-to-prop';
    slot: string;
    prop: string;
};

export type FlagSlotForManualMoveStep = ComponentUsageStepBase & {
    kind: 'flag-slot-for-manual-move';
    slot: string;
    /** The codemod review comment put above the slot; an element that already carries it is not reported again. */
    hint: string;
};

export type SlotToItemsPropStep = ComponentUsageStepBase & {
    kind: 'slot-to-items-prop';
    /** The slot that holds the item elements; defaults to `default`. */
    slot?: string;
    /** The prop that receives the generated items array. */
    prop: string;
    /** The element name of each item inside the slot. */
    itemComponent: string;
    /** The item attribute read as the item name; defaults to `name`. */
    itemNameProp?: string;
    /** The item attribute read as the route when no name is set; defaults to `route`. */
    itemRouteProp?: string;
};

export type RenameEventStep = ComponentUsageStepBase & { kind: 'rename-event'; from: string; to: string };

export type RemoveEventStep = ComponentUsageStepBase & { kind: 'remove-event'; event: string };

export type RenameVModelArgumentStep = ComponentUsageStepBase & {
    kind: 'rename-v-model-argument';
    /** The deprecated v-model argument; `null` matches a plain `v-model`. */
    from: string | null;
    /** The replacement v-model argument; `null` or unset turns it into a plain `v-model`. */
    to?: string | null;
};

export type ComponentUsageStep =
    | RenamePropStep
    | InvertBooleanPropStep
    | BooleanPropToValueStep
    | RemovePropStep
    | ReplacePropStep
    | MapPropValueStep
    | MapOptionsPropKeysStep
    | MissingPropStep
    | RemoveSlotStep
    | SlotToPropStep
    | FlagSlotForManualMoveStep
    | SlotToItemsPropStep
    | RenameEventStep
    | RemoveEventStep
    | RenameVModelArgumentStep;

export type ComponentStep = RenameComponentStep | CodemodRuleStep | ComponentUsageStep;

export type RenameCallStep = UsageStepBase & { kind: 'rename-call'; from: string; to: string };
export type ReplaceObjectOptionStep = UsageStepBase & { kind: 'replace-object-option'; from: string; to: string };
export type MemberCallStep = UsageStepBase & { kind: 'member-call'; from: string; to?: string };
export type ReplaceExtensionStep = UsageStepBase & { kind: 'replace-extension'; from: string[]; to: string };
export type RemoveAssetStep = UsageStepBase & { kind: 'remove-asset' };
export type RemoveTemplateBlockStep = UsageStepBase & { kind: 'remove-template-block'; from: string };
export type RenameTemplateBlockStep = UsageStepBase & { kind: 'rename-template-block'; from: string; to: string };
export type RenameTemplateEventStep = UsageStepBase & {
    kind: 'rename-template-event';
    /** The component that emits the event. */
    component: string;
    from: string;
    to: string;
};
export type RemoveSnippetKeyStep = UsageStepBase & { kind: 'remove-snippet-key'; from: string };
export type RenamePackageStep = UsageStepBase & { kind: 'rename-package'; from: string; to: string };

export type DeprecationUsage =
    | ComponentStep
    | RenameCallStep
    | ReplaceObjectOptionStep
    | MemberCallStep
    | ReplaceExtensionStep
    | RemoveAssetStep
    | RemoveTemplateBlockStep
    | RenameTemplateBlockStep
    | RenameTemplateEventStep
    | RemoveSnippetKeyStep
    | RenamePackageStep;

type MigrationBase<Step extends DeprecationUsage> = {
    /** Unique across the registry; `deprecation:migrate --id` selects migrations by it. */
    id: string;
    deprecatedIn: string;
    removedIn: string;
    description: string;
    references?: DeprecationReference[];
    usage: Step[];
};

export type ComponentMigration = MigrationBase<ComponentStep> & {
    /** The deprecated component. */
    component: string;
    /** The element the usage steps match once a usage is converted; falls back to `component` when unset. */
    replacement?: string;
};

export type GlobalApiMigration = MigrationBase<RenameCallStep> & {
    /** The deprecated global template API, for example `$tc`. */
    api: string;
};

export type JsApiMigration = MigrationBase<ReplaceObjectOptionStep | MemberCallStep>;

export type AssetMigration = MigrationBase<ReplaceExtensionStep | RemoveAssetStep> & {
    /** The deprecated files, relative to the Administration root and starting with `/`. */
    files: string[];
};

export type TemplateBlockMigration = MigrationBase<RemoveTemplateBlockStep | RenameTemplateBlockStep>;
export type TemplateEventMigration = MigrationBase<RenameTemplateEventStep>;
export type SnippetKeyMigration = MigrationBase<RemoveSnippetKeyStep>;
export type PackageMigration = MigrationBase<RenamePackageStep>;

export type DeprecationMigration =
    | ComponentMigration
    | GlobalApiMigration
    | JsApiMigration
    | AssetMigration
    | TemplateBlockMigration
    | TemplateEventMigration
    | SnippetKeyMigration
    | PackageMigration;
