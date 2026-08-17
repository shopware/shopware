/**
 * @sw-package framework
 */

/**
 * The codemod's conversion tables — its primary extension surface.
 *
 * SKIP_OPTIONS / TODO_OPTIONS decide an option's tier, INSTANCE_PROPS drives `this.$xyz` rewrites,
 * LIFECYCLE_HOOKS maps hook names. Anything no table claims becomes a `// TODO(sfc-migration)`
 * comment (partial migration) or a blocker (component skipped) — never a silent guess. Supporting
 * a new feature usually means adding one entry here plus a handler in option-handlers.ts.
 *
 * Mixins have their own table, composables.ts, because a mixin is matched by its registered name
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

// Options whose presence makes the whole component non-migratable.
const SKIP_OPTIONS = new Set([
    'render',
    'renderError',
]);

// Options that are kept as TODO comments; everything unknown lands here too (see classifyOptions).
const TODO_OPTIONS = new Set([
    'metaInfo',
    'shortcuts',
    'provide',
    'filters',
    'compatConfig',
    'components',
    'directives',
    'validations',
    'model',
    'expose',
    'setup',
    'i18n',
    'beforeCreate',
    'beforeRouteEnter',
    'beforeRouteLeave',
    'beforeRouteUpdate',
]);

// `this.$super` / `this.$parent` are structural — the component is skipped entirely.
const SKIP_INSTANCE_PROPS = new Set([
    '$super',
    '$parent',
]);

// `this.$xyz` → replacement identifier; `helper` requests the matching setup declaration/import.
const INSTANCE_PROPS: Record<string, { replacement: string; helper?: HelperName }> = {
    $t: { replacement: 't', helper: 't' },
    $tc: { replacement: 't', helper: 't' },
    $emit: { replacement: 'emit', helper: 'emit' },
    $props: { replacement: 'props', helper: 'props' },
    $router: { replacement: 'router', helper: 'router' },
    $route: { replacement: 'route', helper: 'route' },
    $nextTick: { replacement: 'nextTick', helper: 'nextTick' },
    $slots: { replacement: 'slots', helper: 'slots' },
    $attrs: { replacement: 'attrs', helper: 'attrs' },
};

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

const LIFECYCLE_HOOKS: Record<string, string> = {
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
};

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
    type MemberKind,
    type HelperName,
    type TodoEntry,
    SKIP_OPTIONS,
    TODO_OPTIONS,
    SKIP_INSTANCE_PROPS,
    INSTANCE_PROPS,
    HELPER_SETUP_LINES,
    LIFECYCLE_HOOKS,
    RESERVED_BINDING,
    GENERATED_HELPER_NAMES,
};
