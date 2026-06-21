import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { loadRegistry } = require('../../eslint-rules/deprecation-rules/registry/load-registry');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const adminRoot = path.resolve(__dirname, '../..');
const eslintConfig = path.join(__dirname, 'eslint.config.mjs');

function printHelp() {
    console.log(`Shopware Administration deprecation migration

Usage:
  npm run deprecation:migrate -- --list
  npm run deprecation:migrate -- --id component.sw-button --report
  npm run deprecation:migrate -- --id component.sw-button --fix
  npm run deprecation:migrate -- --all --report
  npm run deprecation:migrate -- --all --fix

Options:
  --help          Show this help.
  --list          List registry ids and fix levels.
  --id <id>       Run one migration id. Can be passed multiple times.
  --all           Run all registry-backed deprecation migrations.
  --report        Report matching deprecations without changing files.
  --fix           Apply available ESLint fixes and report remaining work.

Composer/Docker example:
  docker compose exec web composer npm:admin run deprecation:migrate -- --id component.sw-button --fix
`);
}

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

function getMigrations() {
    const registry = loadRegistry();

    return [
        ...registry.componentApiMigrations,
        ...registry.globalApiMigrations,
        ...registry.jsApiMigrations,
        ...registry.assetMigrations,
        ...registry.templateBlockMigrations,
        ...registry.templateEventMigrations,
        ...registry.snippetKeyMigrations,
        ...registry.packageMigrations,
    ].sort((left, right) => left.id.localeCompare(right.id));
}

function getTransformResult(usage, context = { phase: 'metadata' }) {
    if (typeof usage.transform !== 'function') {
        return null;
    }

    return usage.transform(context);
}

function getHighestFixLevel(fixLevels) {
    if (fixLevels.includes('manual')) {
        return 'manual';
    }

    if (fixLevels.includes('unsafe-auto')) {
        return 'unsafe-auto';
    }

    return 'auto';
}

function getUsageFixLevel(usage) {
    const fixLevels = [
        usage.fix,
        getTransformResult(usage)?.fix,
    ].filter(Boolean);

    return getHighestFixLevel(fixLevels.length > 0 ? fixLevels : ['manual']);
}

function getUsageMessage(usage) {
    return usage.message ?? getTransformResult(usage)?.message ?? null;
}

function getFixLevel(migration) {
    const fixLevels = new Set(migration.usage.map(getUsageFixLevel));

    return getHighestFixLevel([...fixLevels]);
}

function printMigrationList(migrations) {
    migrations.forEach((migration) => {
        const fixLevel = getFixLevel(migration).padEnd(11);
        console.log(`${migration.id.padEnd(56)} ${fixLevel} removed in ${migration.removedIn}`);
    });
}

function printMigrationSummary(migrations) {
    console.log('Selected migrations:');

    migrations.forEach((migration) => {
        const fixLevel = getFixLevel(migration);
        console.log(`- ${migration.id}: ${fixLevel}`);

        migration.usage
            .map(getUsageMessage)
            .filter(Boolean)
            .forEach((usage) => {
                console.log(`  ${usage}`);
            });
    });
}

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

    if (!args.fix && !args.report) {
        args.report = true;
    }

    const knownIds = new Set(migrations.map((migration) => migration.id));
    const unknownIds = args.ids.filter((id) => !knownIds.has(id));

    if (unknownIds.length > 0) {
        throw new Error(`Unknown migration id(s): ${unknownIds.join(', ')}. Run --list to inspect available ids.`);
    }
}

function runEslint(args) {
    const eslintBin = path.join(adminRoot, 'node_modules/.bin/eslint');
    const eslintArgs = [
        '--config',
        eslintConfig,
        '--no-inline-config',
        'src',
        'test',
        'build.ts',
        '--format',
        'stylish',
    ];

    if (args.fix) {
        eslintArgs.push('--fix');
    }

    return spawnSync(eslintBin, eslintArgs, {
        cwd: adminRoot,
        env: {
            ...process.env,
            SHOPWARE_ADMIN_DEPRECATION_IDS: args.ids.join(','),
        },
        stdio: 'inherit',
    });
}

try {
    const args = parseArgs(process.argv.slice(2));
    const migrations = getMigrations();

    if (args.help) {
        printHelp();
        process.exit(0);
    }

    if (args.list) {
        printMigrationList(migrations);
        process.exit(0);
    }

    validateSelection(args, migrations);

    const selectedMigrations = args.all ? migrations : migrations.filter((migration) => args.ids.includes(migration.id));

    printMigrationSummary(selectedMigrations);

    const result = runEslint(args);

    process.exit(result.status ?? 1);
} catch (error) {
    console.error(error.message);
    console.error('Run with --help for usage.');
    process.exit(1);
}
