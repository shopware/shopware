/**
 * @sw-package framework
 */

const FIX_LEVELS_BY_SEVERITY = [
    'manual',
    'unsafe-auto',
];

/**
 * Parses the `deprecation:migrate` command line. Throws on unknown options and on an `--id`
 * without a value, so a typo never silently runs every migration.
 *
 * @example
 * parseArgs(['--id', 'component.sw-button', '--fix']);
 * // { all: false, fix: true, help: false, ids: ['component.sw-button'], list: false, report: false }
 */
function parseArgs(argv) {
    const args = {
        all: false,
        fix: false,
        help: false,
        ids: [],
        list: false,
        report: false,
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

        if (arg === '--report') {
            args.report = true;
            continue;
        }

        throw new Error(`Unknown option "${arg}". Run with --help for usage.`);
    }

    return args;
}

/**
 * Rejects contradicting or empty selections and ids the registry does not know.
 * Without `--fix` the run only reports, so `--report` is optional.
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

    if (args.fix && args.report) {
        throw new Error('Use either --fix or --report, not both.');
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
 * Evaluates a usage's dynamic transform without template context, as the listing has no usage site.
 *
 * @example
 * getTransformResult({ transform: () => ({ kind: 'x', fix: 'manual' }) }); // { kind: 'x', fix: 'manual' }
 */
function getTransformResult(usage, context = {}) {
    if (typeof usage.transform !== 'function') {
        return null;
    }

    return usage.transform(context);
}

/**
 * Picks the least automatic fix level: manual beats unsafe-auto beats auto.
 *
 * @example
 * getHighestFixLevel(['auto', 'unsafe-auto']); // 'unsafe-auto'
 */
function getHighestFixLevel(fixLevels) {
    return FIX_LEVELS_BY_SEVERITY.find((fixLevel) => fixLevels.includes(fixLevel)) ?? 'auto';
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
        getTransformResult(usage)?.fix,
    ].filter(Boolean);

    return getHighestFixLevel(fixLevels.length > 0 ? fixLevels : ['manual']);
}

/**
 * Returns the fix level of a whole migration, the least automatic level of its usage steps.
 *
 * @example
 * getFixLevel({ usage: [{ fix: 'auto' }, { fix: 'unsafe-auto' }] }); // 'unsafe-auto'
 */
function getFixLevel(migration) {
    return getHighestFixLevel(migration.usage.map(getUsageFixLevel));
}

module.exports = {
    getFixLevel,
    getHighestFixLevel,
    getTransformResult,
    getUsageFixLevel,
    parseArgs,
    selectMigrations,
    validateSelection,
};
