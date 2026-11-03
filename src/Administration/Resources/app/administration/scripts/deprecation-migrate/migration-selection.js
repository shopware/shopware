/**
 * @sw-package framework
 */

const FIX_LEVELS_LEAST_AUTOMATIC_FIRST = [
    'manual',
    'unsafe-auto',
    'auto',
];

// The vite image-deprecation plugin warns about asset migrations at build time; no ESLint rule reports them.
const MIGRATIONS_WITHOUT_LINT_RULE = new Set(['assetMigrations']);

/**
 * Parses the `deprecation:migrate` command line. Throws on unknown options and on an `--id`
 * without a value, so a typo never silently runs every migration.
 *
 * @example
 * parseArgs(['--id', 'component.sw-button', '--fix']);
 * // { all: false, fix: true, help: false, ids: ['component.sw-button'], list: false }
 */
function parseArgs(argv) {
    const args = {
        all: false,
        fix: false,
        help: false,
        ids: [],
        list: false,
    };

    for (let index = 0; index < argv.length; index += 1) {
        const arg = argv[index];

        if (arg === '--all') {
            args.all = true;
            continue;
        }

        if (arg === '--fix') {
            args.fix = true;
            continue;
        }

        if (arg === '--help' || arg === '-h') {
            args.help = true;
            continue;
        }

        if (arg === '--id') {
            const id = argv[index + 1];

            if (!id || id.startsWith('--')) {
                throw new Error('--id requires a registry id.');
            }

            args.ids.push(id);
            index += 1;
            continue;
        }

        if (arg.startsWith('--id=')) {
            args.ids.push(arg.slice('--id='.length));
            continue;
        }

        if (arg === '--list') {
            args.list = true;
            continue;
        }

        throw new Error(`Unknown option "${arg}".`);
    }

    return args;
}

/**
 * Rejects contradicting or empty selections and ids the registry does not know.
 *
 * @example
 * validateSelection(parseArgs(['--all', '--id', 'x']), migrations); // throws 'Use either --all or --id, not both.'
 */
function validateSelection(args, migrations) {
    if (args.all && args.ids.length > 0) {
        throw new Error('Use either --all or --id, not both.');
    }

    if (!args.all && args.ids.length === 0) {
        throw new Error('Choose --all, --id <id>, --list, or --help.');
    }

    const knownIds = new Set(migrations.map((migration) => migration.id));
    const unknownIds = args.ids.filter((id) => !knownIds.has(id));

    if (unknownIds.length > 0) {
        throw new Error(`Unknown migration id(s): ${unknownIds.join(', ')}. Run --list to inspect available ids.`);
    }
}

/**
 * Returns the migrations a validated selection runs: every migration for `--all`, otherwise the listed ids.
 *
 * @example
 * selectMigrations({ all: false, ids: ['a'] }, [{ id: 'a' }, { id: 'b' }]); // [{ id: 'a' }]
 */
function selectMigrations(args, migrations) {
    return args.all ? migrations : migrations.filter((migration) => args.ids.includes(migration.id));
}

/**
 * Evaluates a usage's dynamic transform with an empty context, because the listing and the summary
 * describe a migration without a concrete usage site.
 *
 * @example
 * getContextFreeTransformResult({ transform: () => ({ kind: 'x', fix: 'manual' }) }); // { kind: 'x', fix: 'manual' }
 */
function getContextFreeTransformResult(usage) {
    if (typeof usage.transform !== 'function') {
        return null;
    }

    return usage.transform({});
}

/**
 * Picks the least automatic of the given fix levels, so a migration is only as automatic as its weakest step.
 *
 * @example
 * getLeastAutomaticFixLevel(['auto', 'unsafe-auto']); // 'unsafe-auto'
 */
function getLeastAutomaticFixLevel(fixLevels) {
    return FIX_LEVELS_LEAST_AUTOMATIC_FIRST.find((fixLevel) => fixLevels.includes(fixLevel));
}

/**
 * Returns the fix level of one usage step from its own `fix` and its transform's. A usage that
 * declares neither has no automatic fix, so it counts as manual.
 *
 * @example
 * getUsageFixLevel({ kind: 'remove-prop' }); // 'manual'
 */
function getUsageFixLevel(usage) {
    const fixLevels = [
        usage.fix,
        getContextFreeTransformResult(usage)?.fix,
    ].filter(Boolean);

    return getLeastAutomaticFixLevel(fixLevels.length > 0 ? fixLevels : ['manual']);
}

/**
 * Returns the fix level of a whole migration, the least automatic level of its usage steps.
 *
 * @example
 * getFixLevel({ usage: [{ fix: 'auto' }, { fix: 'unsafe-auto' }] }); // 'unsafe-auto'
 */
function getFixLevel(migration) {
    return getLeastAutomaticFixLevel(migration.usage.map(getUsageFixLevel));
}

/**
 * Lists the registry migrations `deprecation:migrate` can run, sorted by id: every migration list the
 * registry exports, except the ones no ESLint rule reports.
 *
 * @example
 * getRunnableMigrations(loadRegistry()).map((migration) => migration.id);
 * // ['component.sw-alert', …, 'template-event.vue3-v-model-events']
 */
function getRunnableMigrations(registry) {
    return Object.entries(registry)
        .filter(([name, migrations]) => Array.isArray(migrations) && !MIGRATIONS_WITHOUT_LINT_RULE.has(name))
        .flatMap(([, migrations]) => migrations)
        .sort((left, right) => left.id.localeCompare(right.id));
}

module.exports = {
    getContextFreeTransformResult,
    getFixLevel,
    getLeastAutomaticFixLevel,
    getRunnableMigrations,
    getUsageFixLevel,
    parseArgs,
    selectMigrations,
    validateSelection,
};
