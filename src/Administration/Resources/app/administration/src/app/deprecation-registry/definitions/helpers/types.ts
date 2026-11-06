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
    /** Inserts a codemod review comment with `text` directly before the target, without splitting its line. */
    insertCodemodCommentBefore(fixer: Fixer, target: TemplateNode, text: string): Fix;
    /**
     * Moves the HTML comments inside the ranges above the element, for fixes that remove that content.
     *
     * @example
     * fix: (fixer) => [removeSlot(fixer), ...api.keepCommentsOf(fixer, [slot.range])]
     */
    keepCommentsOf(fixer: Fixer, ranges: Array<[number, number]>): Fix[];
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

export type BooleanPropToPropValueStep = ComponentUsageStepBase & {
    kind: 'boolean-prop-to-prop-value';
    /** The deprecated boolean prop. */
    fromProp: string;
    /** The prop that takes a value instead. */
    toProp: string;
    /** The static value `toProp` gets when `fromProp` is set. */
    withValue: string;
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
    /** Review note for a boolean prop whose "false" behaviour the new component lost, unless it is a static `true`. */
    reviewUnlessTrue?: string;
};

export type ReplacePropStep = ComponentUsageStepBase & {
    kind: 'replace-prop';
    prop: string;
    with: PropReplacement;
};

export type ReplacePropValueStep = ComponentUsageStepBase & {
    kind: 'replace-prop-value';
    prop: string;
    /** The static value that is deprecated. */
    fromValue: string;
    /** The value that replaces it; unset for a value without replacement, which only a manual step can have. */
    toValue?: string;
    /** A boolean prop the new value needs, for example `ghost` when `variant="ghost"` becomes `variant="primary"`. */
    alsoAddProp?: string;
};

export type RenameArrayPropKeysStep = ComponentUsageStepBase & {
    kind: 'rename-array-prop-keys';
    /** The prop that receives the array of objects. */
    prop: string;
    /** Maps each deprecated object key to its replacement key. */
    renameKeys: Record<string, string>;
    /** The step is skipped when the element sets one of these props, for example a prop that names the keys. */
    unlessElementHas?: string[];
    /** Reported instead of a fix when the options expression cannot be rewritten automatically. */
    unsafeMessage?: string;
};

export type AddPropWhenMissingStep = ComponentUsageStepBase & {
    kind: 'add-prop-when-missing';
    prop: string;
    /** The value source the fix writes into the added attribute; unset adds a valueless boolean attribute. */
    value?: string;
    /** Adds the prop as a `:prop` binding instead of a static attribute. */
    bind?: boolean;
    /** Where the prop goes in the start tag; defaults to `before-end`. */
    insertPosition?: 'after-name' | 'before-end';
    /** The step is skipped when the element already sets one of these props; defaults to `[prop]`. */
    unlessElementHas?: string[];
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

export type SlotTextToPropStep = ComponentUsageStepBase & {
    kind: 'slot-text-to-prop';
    fromSlot: string;
    toProp: string;
};

export type FlagSlotForManualMoveStep = ComponentUsageStepBase & {
    kind: 'flag-slot-for-manual-move';
    slot: string;
    /** What a developer has to do with the slot content. */
    hint: string;
};

/**
 * Where a field of an array item takes its value from: the text of the child element, or one of its
 * attributes. Build it with `fromText()` or `fromAttribute()`.
 */
export type ItemFieldSource =
    | {
          from: 'attribute';
          attribute: string;
          /** A child element without the attribute needs a manual migration instead of an item without the field. */
          required?: boolean;
          /** Writes an empty static value as `null`, for components that turned an empty value into null. */
          emptyAsNull?: boolean;
          /** Review note for a bound value, for example when the new component keeps its type and the old one did not. */
          boundReview?: string;
      }
    | { from: 'text' };

export type ChildElementsToArrayPropStep = ComponentUsageStepBase & {
    kind: 'child-elements-to-array-prop';
    /** The slot that holds the child elements; defaults to `default`. */
    inSlot?: string;
    /** The element name of each child that becomes an array item, for example `option`. */
    childElement: string;
    /** The prop that receives the array. */
    arrayProp: string;
    /** The fields of each array item and where they come from. */
    eachItem: Record<string, ItemFieldSource>;
    /**
     * Props that name the key of a field. A single `v-for` child whose fields are plain keys of the list
     * entries then passes the list as it is, instead of mapping it into new objects.
     */
    forBoundList?: Record<string, string>;
};

/**
 * One place the old component could take a value from. Build it with `fromSlot()` or `fromProp()`.
 */
export type PropSource =
    | {
          from: 'slot';
          slot: string;
          /** Reads `attribute` of the single child element of these names, instead of the slot's text. */
          childElement?: string[];
          attribute?: string;
      }
    | { from: 'prop'; prop: string };

/** Several sources, of which the first one the element uses wins. Build it with `firstFoundOf()`. */
export type FirstFoundOf = { from: 'first-found-of'; sources: PropSource[] };

export type PropFromSourceStep = ComponentUsageStepBase & {
    kind: 'prop-from-source';
    /** The prop of the new component. */
    toProp: string;
    /** Where the old component took the value from. */
    source: PropSource | FirstFoundOf;
    /** The expression the old component showed when no source was set, or a bound source was null or undefined. */
    oldComponentFallback?: string;
    /** A boolean prop of the old component that hid the value; `valueWhenOff` is passed while it is false. */
    oldComponentToggle?: { prop: string; valueWhenOff: string };
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
    | BooleanPropToPropValueStep
    | RemovePropStep
    | ReplacePropStep
    | ReplacePropValueStep
    | RenameArrayPropKeysStep
    | AddPropWhenMissingStep
    | RemoveSlotStep
    | RenameSlotStep
    | SlotTextToPropStep
    | FlagSlotForManualMoveStep
    | ChildElementsToArrayPropStep
    | PropFromSourceStep
    | RenameEventStep
    | RemoveEventStep
    | RenameVModelArgumentStep;

export type ComponentStep = RenameComponentStep | ComponentUsageStep;

export type RenameCallStep = UsageStepBase & { kind: 'rename-call'; from: string; to: string };
export type ReplaceObjectOptionStep = UsageStepBase & { kind: 'replace-object-option'; from: string; to: string };
export type MemberCallStep = UsageStepBase & { kind: 'member-call'; from: string; to?: string };
export type ReplaceExtensionStep = UsageStepBase & { kind: 'replace-extension'; from: string[]; to: string };
export type RemoveAssetStep = UsageStepBase & { kind: 'remove-asset' };

/** Limits a template block step to templates of these components, see `componentScope`. */
type TemplateBlockScope = {
    /** The component whose template defines the block, and the components that inherit its template. */
    components?: string[];
    /**
     * Another core template defines a block of the same name, so only a template that belongs to one
     * of `components` overrides this one.
     */
    onlyInComponents?: boolean;
};

export type RemoveTemplateBlockStep = UsageStepBase &
    TemplateBlockScope & {
        kind: 'remove-template-block';
        from: string;
        /** The block, or other place, an override moves to; named in the report and the review comment. */
        moveTo?: string;
    };
export type RenameTemplateBlockStep = UsageStepBase &
    TemplateBlockScope & { kind: 'rename-template-block'; from: string; to: string };

/**
 * A member of a JavaScript API, matched where it is read, called or destructured. `from` is a dotted
 * path such as `Shopware.State` or `this.$store`, or `*.name` for the member on any object.
 */
export type MemberUsageStep = UsageStepBase & { kind: 'member-usage'; from: string; to?: string };

/** A method of an injected service: `this.<service>.<method>`, `<service>.<method>` or `Shopware.Service('<service>').<method>`. */
export type ServiceMethodStep = UsageStepBase & {
    kind: 'service-method';
    service: string;
    method: string;
    to?: string;
    /** Only reports calls that leave out this argument (zero-based), or pass `null` or `undefined` for it. */
    missingArgument?: number;
};

/** A member of a Pinia store: `Shopware.Store.get('<store>').<member>`. */
export type StoreMemberStep = UsageStepBase & { kind: 'store-member'; store: string; member: string; to?: string };

/**
 * A member a core component loses, used by its overrides and extensions: read through `this`,
 * through `this.$super()` or destructuring, defined in the override's options, or used in its template.
 * An override is a `Component.override/extend()` of one of the components, or a file in a folder or
 * with a name of one of them, like `sw-order-list/index.js` or `sw-order-list.html.twig`.
 */
export type ComponentMemberStep = UsageStepBase & {
    kind: 'component-member';
    components: string[];
    member: string;
    to?: string;
    /** An expression that replaces a script read of the member, the fix of an `auto` step. Templates stay manual. */
    replaceInScript?: string;
};

/** An injected key: `inject: ['<key>']`, `inject: { local: '<key>' }` or `inject('<key>')`. */
export type InjectKeyStep = UsageStepBase & { kind: 'inject-key'; from: string; to?: string };

/** A removed component: its `Component.override/extend()` calls, its tags and string literals naming it. */
export type ComponentReferenceStep = UsageStepBase & {
    kind: 'component-reference';
    component: string;
    to?: string;
    /** False when a component migration already reports the tags; defaults to true. */
    templates?: boolean;
};

/** A string literal with exactly this value, e.g. a removed option value. */
export type StringValueStep = UsageStepBase & { kind: 'string-value'; from: string; to?: string };

/** A key of an object literal, e.g. a request option. */
export type ObjectKeyStep = UsageStepBase & { kind: 'object-key'; from: string; to?: string };

/** An import or `require()` of a module path, optionally only of some named specifiers. */
export type ImportSourceStep = UsageStepBase & {
    kind: 'import-source';
    from: string;
    specifiers?: string[];
    to?: string;
};

/** A file with a name matching `pattern`, e.g. a build config of the extension. */
export type ConfigFileStep = UsageStepBase & { kind: 'config-file'; pattern: string; to?: string };

export type ApiUsageStep =
    | MemberUsageStep
    | ServiceMethodStep
    | StoreMemberStep
    | ComponentMemberStep
    | InjectKeyStep
    | ComponentReferenceStep
    | StringValueStep
    | ObjectKeyStep
    | ImportSourceStep
    | ConfigFileStep;
export type RenameTemplateEventStep = UsageStepBase & {
    kind: 'rename-template-event';
    /** The component that emits the event. */
    component: string;
    from: string;
    to: string;
};
export type RemoveSnippetKeyStep = UsageStepBase & { kind: 'remove-snippet-key'; from: string };
export type RenameSnippetKeyStep = UsageStepBase & { kind: 'rename-snippet-key'; from: string; to: string };
export type RenamePackageStep = UsageStepBase & { kind: 'rename-package'; from: string; to: string };

type DeprecationUsage =
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
    | RenameSnippetKeyStep
    | RenamePackageStep
    | ApiUsageStep;

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
 * onConvert: [renameComponent({ from: 'sw-button', to: 'mt-button' }), addPropWhenMissing({ prop: 'variant', value: 'secondary' })],
 * onEveryUsage: [replacePropValue({ prop: 'variant', fromValue: 'danger', toValue: 'critical' })],
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

export type JsApiMigration = MigrationBase<ReplaceObjectOptionStep | MemberCallStep | ApiUsageStep>;

export type AssetMigration = MigrationBase<ReplaceExtensionStep | RemoveAssetStep> & {
    /** The deprecated files, relative to the Administration root and starting with `/`. */
    files: string[];
};

export type TemplateBlockMigration = MigrationBase<RemoveTemplateBlockStep | RenameTemplateBlockStep>;
export type TemplateEventMigration = MigrationBase<RenameTemplateEventStep>;
export type SnippetKeyMigration = MigrationBase<RemoveSnippetKeyStep | RenameSnippetKeyStep>;
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
