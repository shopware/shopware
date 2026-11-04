import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { loadRegistry } = require('../../eslint-rules/deprecation-rules/registry/load-registry');
const {
    getFixLevel,
    getRunnableMigrations,
    parseArgs,
    selectMigrations,
    validateSelection,
} = require('./migration-selection');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const adminRoot = path.resolve(__dirname, '../..');
const eslintConfig = path.join(__dirname, 'eslint.config.mjs');

function printHelp() {
    console.log(`Shopware Administration deprecation migration

Usage:
  npm run deprecation:migrate -- --list
  npm run deprecation:migrate -- --id component.sw-button
  npm run deprecation:migrate -- --id component.sw-button --fix
  npm run deprecation:migrate -- --all
  npm run deprecation:migrate -- --all --fix
  npm run deprecation:migrate -- --all --fix --level review

Options:
  --help          Show this help.
  --list          List registry ids and fix levels.
  --id <id>       Run one migration id. Can be passed multiple times.
  --all           Run all registry-backed deprecation migrations.
  --fix           Apply available ESLint fixes and report remaining work.
                  Without --fix, matching deprecations are only reported.
  --level <level> How much --fix may change (default: safe). Run the levels in
                  this order and commit after each one:
                  safe     Only fixes that keep the behaviour. No review needed.
                  review   Also fixes that leave a "TODO Codemod" review comment.
                           Nothing is converted that needs manual work.
                  partial  Converts everything and leaves a "TODO Codemod" comment
                           on each part that needs manual work.
                  manual   Changes no code and puts a "TODO Codemod" comment above
                           every deprecated usage.

Asset deprecations (for example PNG/JPG to WebP) are reported by the admin build, not by this command.

Composer/Docker example:
  docker compose exec web composer npm:admin run deprecation:migrate -- --id component.sw-button --fix
`);
}

function getMigrations() {
    return getRunnableMigrations(loadRegistry());
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
            .map((usage) => usage.message)
            .filter(Boolean)
            .forEach((message) => {
                console.log(`  ${message}`);
            });
    });
}

function runEslint(args) {
    const eslintBin = path.join(adminRoot, 'node_modules/.bin/eslint');
    const eslintArgs = [
        '--config',
        eslintConfig,
        // Inline disables name rules this minimal config does not load, which ESLint reports as errors.
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
            SHOPWARE_ADMIN_DEPRECATION_LEVEL: args.level,
        },
        stdio: 'inherit',
    });
}

try {
    const args = parseArgs(process.argv.slice(2));

    if (args.help) {
        printHelp();
        process.exit(0);
    }

    const migrations = getMigrations();

    if (args.list) {
        printMigrationList(migrations);
        process.exit(0);
    }

    validateSelection(args, migrations);

    const selectedMigrations = selectMigrations(args, migrations);

    printMigrationSummary(selectedMigrations);

    const result = runEslint(args);

    if (result.error) {
        console.error(`Could not run ESLint: ${result.error.message}`);
    }

    process.exit(result.status ?? 1);
} catch (error) {
    console.error(error.message);
    console.error('Run with --help for usage.');
    process.exit(1);
}
