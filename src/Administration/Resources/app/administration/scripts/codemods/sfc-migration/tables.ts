/**
 * @sw-package framework
 */

/**
 * The conversion tables. Anything no table claims becomes a TODO (partial) or a blocker (skipped),
 * never a silent guess.
 */

type MemberKind = 'prop' | 'data' | 'computed' | 'method' | 'inject';

type HelperName = 't' | 'router' | 'route' | 'emit' | 'props' | 'slots' | 'attrs' | 'nextTick';

type TodoEntry = {
    reason: string;
    code?: string;
    /** `FIX`: the emitted code does not run as it stands. `VERIFY`: it runs, equivalence is unproven. */
    mode?: 'FIX' | 'VERIFY';
    explanation?: string;
    checks?: string[];
    /** Rendered above its declaration by the section that writes it, not in the file-wide groups. */
    anchored?: boolean;
};

type ReportKind = 'skip' | 'todo';

/** Null prototype: a member called `constructor` or `toString` must not find `Object.prototype`'s. */
function sourceKeyed<T>(entries: Record<string, T>): Record<string, T> {
    return Object.assign(Object.create(null) as Record<string, T>, entries);
}

// For options no handler claims; an option absent here too is reported as unknown.
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

const SKIP_INSTANCE_PROPS = new Set(['$super', '$parent']);

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

// A member named like one of these would shadow the helper the rewrite emits for `this.$xyz`.
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
    GENERATED_HELPER_NAMES,
};
