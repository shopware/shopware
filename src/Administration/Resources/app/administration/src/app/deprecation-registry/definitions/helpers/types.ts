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
    removeRange(range: [number, number]): Fix;
    replaceText(target: TemplateNode, text: string): Fix;
    replaceTextRange(range: [number, number], text: string): Fix;
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
    /** What the fix is, for the run level of `deprecation:migrate`; see `withRunLevel`. */
    fixLevel?: FixLevel;
    /** The review comment for this usage when the run level asks for one. */
    reviewComment?: string;
    /** A review of the conversion itself, which the conversion's review comment carries. */
    conversionNote?: string;
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
    /** Removes an attribute with the whitespace that separates it, leaving no double space or empty line. */
    removeAttribute(fixer: Fixer, attribute: TemplateNode): Fix;
    /** Tells whether the codemod converted this element from the migration's deprecated component. */
    isConverted(): boolean;
    /** Inserts a codemod review comment with `text` before the target, on its own line with the target's indentation. */
    insertCodemodCommentBefore(fixer: Fixer, target: TemplateNode, text: string): Fix;
    ast: {
        findMatchingPropAttribute(node: TemplateNode, propName: string): TemplateNode | undefined;
        hasMatchingPropAttribute(node: TemplateNode, propName: string): boolean;
        findMatchingEventAttribute(node: TemplateNode, eventName: string): TemplateNode | undefined;
        findMatchingVModelAttribute(node: TemplateNode, argumentName: string | null): TemplateNode | undefined;
        findSlot(node: TemplateNode, slotName: string): TemplateNode | undefined;
        hasCodemodComment(node: TemplateNode, text: string): boolean;
        /** Tells whether a comment containing `text` sits directly above the element. */
        hasLeadingComment(node: TemplateNode, text: string): boolean;
        getAttributeValueSource(attribute: TemplateNode): string | null;
        getDirectiveName(attribute: TemplateNode): string | null;
        getFirstElementChildWithoutSlot(node: TemplateNode): TemplateNode | undefined;
        getStaticAttributeName(attribute: TemplateNode): string | null;
        getDirectiveArgumentName(attribute: TemplateNode): string | null;
        /** The `v-bind="object"` attribute, which can hide or override any prop. */
        findObjectVBindAttribute(node: TemplateNode): TemplateNode | null | undefined;
        /** The `v-on="object"` attribute, which can hide or override any listener. */
        findObjectVOnAttribute(node: TemplateNode): TemplateNode | null | undefined;
        /** Slot content as a prop value; null when it holds markup or Twig, which a prop cannot carry. */
        getSlotContentValue(slot: TemplateNode): PropValue | null;
        /** Like `getSlotContentValue`, for content nodes written without a slot template. */
        getContentValue(nodes: TemplateNode[]): PropValue | null;
        /** The range of a child node including the whitespace before it, so removing it leaves no empty line. */
        getRemovalRange(node: TemplateNode): [number, number];
        /** Attribute text for a prop value; null for an expression that mixes both quote styles. */
        toPropAttribute(name: string, value: Exclude<PropValue, { kind: 'none' }>): string | null;
    };
};

/** A prop value read from template content: plain text, a JavaScript expression, or nothing. */
export type PropValue = { kind: 'static'; text: string } | { kind: 'expr'; code: string } | { kind: 'none' };

type UsageStepBase = {
    fix: FixLevel;
    /** Replaces the default "The "<api>" API is deprecated." headline of the report. */
    message?: string;
};

/** A step `no-deprecated-component-usage` runs on every element of the migration's replacement component. */
type ComponentUsageStepBase = UsageStepBase & {
    /** Set by `componentMigration` for `onConvert` steps: they run only on elements the codemod converted. */
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
    /**
     * Review note for a boolean prop whose "true" behaviour the new component lost. A prop bound to a
     * literal `false` is removed without it.
     */
    reviewWhenTrue?: string;
    /** The old component behaved as if the prop was true when it was not set; such elements get the review note too. */
    trueByDefault?: boolean;
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
    /** The value source the fix writes into the added attribute; unset adds a valueless boolean attribute. */
    value?: string;
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
    /** Why a slot with content needs a manual move; defaults to a generic "not rendered anymore" hint. */
    hint?: string;
};

export type RenameSlotStep = ComponentUsageStepBase & {
    kind: 'rename-slot';
    from: string;
    to: string;
};

export type SlotToPropStep = ComponentUsageStepBase & {
    kind: 'slot-to-prop';
    slot: string;
    prop: string;
};

export type FlagSlotForManualMoveStep = ComponentUsageStepBase & {
    kind: 'flag-slot-for-manual-move';
    slot: string;
    /** What a developer has to do with the slot content. */
    hint: string;
};

/**
 * Where a field of a generated item takes its value from: an attribute of the item element, or its
 * text content.
 *
 * @example
 * { attribute: 'value', emptyAsNull: true } // <option value="">… → value: null
 * { content: true } // <option>{{ $t('a') }}</option> → label: $t('a')
 */
export type ItemFieldSource =
    | {
          attribute: string;
          /** An item without the attribute needs a manual migration instead of an item without the field. */
          required?: boolean;
          /** Writes an empty static value as `null`, for components that turned an empty value into null. */
          emptyAsNull?: boolean;
          /** Review note for a bound value, for example when the new component keeps its type and the old one did not. */
          boundReview?: string;
      }
    | { content: true };

export type SlotToItemsPropStep = ComponentUsageStepBase & {
    kind: 'slot-to-items-prop';
    /** The slot that holds the item elements; defaults to `default`. */
    slot?: string;
    /** The prop that receives the generated items array. */
    prop: string;
    /** The element name of each item inside the slot, for example `option`. */
    item: string;
    /** The fields of each generated item and where they come from. */
    fields: Record<string, ItemFieldSource>;
    /**
     * Props that name the item key of a field. A `v-for` list whose fields are plain keys of the list
     * entries is then passed as it is, instead of being mapped into new objects.
     */
    keyProps?: Record<string, string>;
};

/**
 * One place a value of the old component could come from.
 *
 * @example
 * { slot: 'default' } // the text of the default slot
 * { slot: 'icon', element: ['mt-icon'], attribute: 'name' } // the name of the single icon in the icon slot
 * { prop: 'subline' }
 */
export type PropSource = { slot: string; element?: string[]; attribute?: string } | { prop: string };

export type PropFromSourcesStep = ComponentUsageStepBase & {
    kind: 'prop-from-sources';
    /** The prop of the new component. */
    prop: string;
    /** Where the old component took the value from, first match wins. */
    sources: PropSource[];
    /** The expression the old component used when no source was set. */
    fallback?: string;
    /** A boolean prop of the old component that hid the value; `otherwise` is passed while it is false. */
    condition?: { prop: string; otherwise: string };
    /** The new prop renders plain text, so static HTML in the value needs a review. */
    plainText?: boolean;
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
    | RenameSlotStep
    | SlotToPropStep
    | FlagSlotForManualMoveStep
    | SlotToItemsPropStep
    | PropFromSourcesStep
    | RenameEventStep
    | RemoveEventStep
    | RenameVModelArgumentStep;

export type ComponentStep = RenameComponentStep | ComponentUsageStep;

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

/**
 * Builder input of `componentMigration`. A converted element gets both step lists, a hand-written
 * usage of the replacement only `onEveryUsage`.
 *
 * @example
 * onConvert: [renameComponent({ from: 'sw-button', to: 'mt-button' }), missingProp({ prop: 'variant', value: 'secondary' })],
 * onEveryUsage: [mapPropValue({ prop: 'variant', from: 'danger', to: 'critical' })],
 */
export type ComponentMigrationConfig = Omit<ComponentMigration, 'usage'> & {
    /** The conversion: the component rename, then the steps that carry the old component's behaviour over. */
    onConvert?: [RenameComponentStep, ...ComponentUsageStep[]];
    /** Steps that run on every element of the replacement, converted or written by hand. */
    onEveryUsage?: ComponentUsageStep[];
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
