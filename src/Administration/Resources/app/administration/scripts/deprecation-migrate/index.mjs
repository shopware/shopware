import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { loadRegistry } = require('../../eslint-rules/deprecation-rules/registry/load-registry');
const { getFixLevel, getTransformResult, parseArgs, selectMigrations, validateSelection } = require('./migration-selection');

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

function getUsageMessage(usage) {
    return usage.message ?? getTransformResult(usage)?.message ?? null;
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

    const selectedMigrations = selectMigrations(args, migrations);

    printMigrationSummary(selectedMigrations);

    const result = runEslint(args);

    process.exit(result.status ?? 1);
} catch (error) {
    console.error(error.message);
    console.error('Run with --help for usage.');
    process.exit(1);
}
