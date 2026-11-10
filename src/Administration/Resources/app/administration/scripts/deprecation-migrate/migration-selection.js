/**
 * @sw-package framework
 */

const { RUN_LEVELS } = require('../../eslint-rules/deprecation-rules/registry/run-level');

const FIX_LEVELS_LEAST_AUTOMATIC_FIRST = [
    'manual',
    'unsafe-auto',
    'auto',
];

/**
 * Parses the `deprecation:migrate` command line. Throws on unknown options and on an `--id`
 * without a value, so a typo never silently runs every migration.
 *
 * @example
 * parseArgs(['--id', 'component.sw-button', '--fix', '--level', 'review']);
 * // { all: false, fix: true, help: false, ids: ['component.sw-button'], level: 'review', list: false }
 */
function parseArgs(argv) {
    const args = {
        all: false,
        fix: false,
        help: false,
        ids: [],
        level: 'safe',
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

        if (arg === '--level' || arg.startsWith('--level=')) {
            const level = arg === '--level' ? argv[index + 1] : arg.slice('--level='.length);

            if (!RUN_LEVELS.includes(level)) {
                throw new Error(`--level requires one of: ${RUN_LEVELS.join(', ')}.`);
            }

            args.level = level;
            index += arg === '--level' ? 1 : 0;
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
 * Picks the least automatic of the given fix levels, so a migration is only as automatic as its weakest step.
 *
 * @example
 * getLeastAutomaticFixLevel(['auto', 'unsafe-auto']); // 'unsafe-auto'
 */
function getLeastAutomaticFixLevel(fixLevels) {
    return FIX_LEVELS_LEAST_AUTOMATIC_FIRST.find((fixLevel) => fixLevels.includes(fixLevel));
}

/**
 * Returns the fix level of a whole migration, the least automatic level of its usage steps.
 *
 * @example
 * getFixLevel({ usage: [{ fix: 'auto' }, { fix: 'unsafe-auto' }] }); // 'unsafe-auto'
 */
function getFixLevel(migration) {
    return getLeastAutomaticFixLevel(migration.usage.map((usage) => usage.fix));
}

/**
 * Lists the registry migrations `deprecation:migrate` can run, sorted by id: every migration list the
 * registry exports, as each one has an ESLint rule that reports it.
 *
 * @example
 * getRunnableMigrations(loadRegistry()).map((migration) => migration.id);
 * // ['api.vuex', 'asset.admin-png-jpg-to-webp', 'component.sw-alert', …, 'template-event.vue3-v-model-events']
 */
function getRunnableMigrations(registry) {
    return Object.values(registry)
        .filter((migrations) => Array.isArray(migrations))
        .flat()
        .sort((left, right) => left.id.localeCompare(right.id));
}

module.exports = {
    getFixLevel,
    getLeastAutomaticFixLevel,
    getRunnableMigrations,
    parseArgs,
    selectMigrations,
    validateSelection,
};
