/**
 * @sw-package framework
 */

/**
 * The codemod's conversion tables — its primary extension surface.
 *
 * OPTION_TIERS decides an unhandled option's tier, INSTANCE_PROPS drives `this.$xyz` rewrites,
 * LIFECYCLE_HOOKS maps hook names. Anything no table claims becomes a `// TODO(sfc-migration)`
 * comment (partial migration) or a blocker (component skipped) — never a silent guess. Supporting
 * a new feature usually means adding one entry here plus a handler in option-handlers.ts.
 *
 * Mixins have their own table, composables/, because a mixin is matched by its registered name
 * rather than by an option key.
 */

type MemberKind = 'prop' | 'data' | 'computed' | 'method' | 'inject';

type HelperName = 't' | 'router' | 'route' | 'emit' | 'props' | 'slots' | 'attrs' | 'nextTick';

type TodoEntry = {
    reason: string;
    code?: string;
    /**
     * Tells the reader of a draft what the TODO asks of them: `FIX` means the emitted code does not
     * run as it stands, `VERIFY` means it does and only its equivalence is unproven.
     */
    mode?: 'FIX' | 'VERIFY';
    /** Why the mode applies — what the reader has to write, or what the codemod could not prove. */
    explanation?: string;
    /** Review points of a TODO that asks the reader to check emitted code instead of writing missing code. */
    checks?: string[];
    /**
     * Emitted above the single declaration it is about, by whichever section writes that
     * declaration — the file-wide TODO groups leave it out.
     */
    anchored?: boolean;
};

/** The two ways the codemod says "I could not convert this": refuse the component, or leave a note. */
type ReportKind = 'skip' | 'todo';

/**
 * A lookup table keyed by names read out of component source. The null prototype is what makes the
 * lookup safe: a member or option called `constructor`, `hasOwnProperty`, `toString`, … would
 * otherwise resolve to `Object.prototype`'s and be taken for a table entry.
 */
function sourceKeyed<T>(entries: Record<string, T>): Record<string, T> {
    return Object.assign(Object.create(null) as Record<string, T>, entries);
}

// Tier for options no handler claims: 'skip' makes the whole component non-migratable, 'todo' keeps
// the option as a comment. Anything absent from this table and unclaimed is an unknown option — also
// a TODO, but under a different reason (see classifyOptions).
const OPTION_TIERS: Record<string, ReportKind> = sourceKeyed<ReportKind>({
    render: 'skip',
    renderError: 'skip',
    metaInfo: 'todo',
    shortcuts: 'todo',
    provide: 'todo',
    filters: 'todo',
    compatConfig: 'todo',
    components: 'todo',
    directives: 'todo',
    validations: 'todo',
    model: 'todo',
    expose: 'todo',
    setup: 'todo',
    i18n: 'todo',
    beforeCreate: 'todo',
    beforeRouteEnter: 'todo',
    beforeRouteLeave: 'todo',
    beforeRouteUpdate: 'todo',
});

// `this.$super` / `this.$parent` are structural — the component is skipped entirely.
const SKIP_INSTANCE_PROPS = new Set([
    '$super',
    '$parent',
]);

// `this.$xyz` → replacement identifier; `helper` requests the matching setup declaration/import.
const INSTANCE_PROPS: Record<string, { replacement: string; helper?: HelperName }> = sourceKeyed<{
    replacement: string;
    helper?: HelperName;
}>({
    $t: { replacement: 't', helper: 't' },
    $tc: { replacement: 't', helper: 't' },
    $emit: { replacement: 'emit', helper: 'emit' },
    $props: { replacement: 'props', helper: 'props' },
    $router: { replacement: 'router', helper: 'router' },
    $route: { replacement: 'route', helper: 'route' },
    $nextTick: { replacement: 'nextTick', helper: 'nextTick' },
    $slots: { replacement: 'slots', helper: 'slots' },
    $attrs: { replacement: 'attrs', helper: 'attrs' },
});

const HELPER_SETUP_LINES: Record<HelperName, string | null> = {
    t: 'const { t } = useI18n();',
    router: 'const router = useRouter();',
    route: 'const route = useRoute();',
    slots: 'const slots = useSlots();',
    attrs: 'const attrs = useAttrs();',
    nextTick: null,
    emit: null,
    props: null,
};

const LIFECYCLE_HOOKS: Record<string, string> = sourceKeyed<string>({
    beforeMount: 'onBeforeMount',
    mounted: 'onMounted',
    beforeUpdate: 'onBeforeUpdate',
    updated: 'onUpdated',
    beforeUnmount: 'onBeforeUnmount',
    beforeDestroy: 'onBeforeUnmount',
    unmounted: 'onUnmounted',
    destroyed: 'onUnmounted',
    activated: 'onActivated',
    deactivated: 'onDeactivated',
});

// Top-level binding names the Shopware setup transform reserves or that would shadow a generated
// helper. Producing one of these is a hard skip.
const RESERVED_BINDING = /^(__swSetup|__swOverride$|__proto__$|Shopware$)/;
const GENERATED_HELPER_NAMES = new Set([
    't',
    'router',
    'route',
    'emit',
    'props',
    'slots',
    'attrs',
    'nextTick',
]);

export {
    sourceKeyed,
    type MemberKind,
    type HelperName,
    type TodoEntry,
    type ReportKind,
    OPTION_TIERS,
    SKIP_INSTANCE_PROPS,
    INSTANCE_PROPS,
    HELPER_SETUP_LINES,
    LIFECYCLE_HOOKS,
    RESERVED_BINDING,
    GENERATED_HELPER_NAMES,
};
