/**
 * Component build orchestrator (CommonJS, runs with plain Node.js).
 *
 * Reads var/plugins.json (written by `bin/console bundle:dump`) and runs a Vite
 * component build for every bundle that declares a `components.path`.
 *
 * Build strategy per bundle:
 *  - If the bundle provides `Resources/app/storefront/vite.components.config.ts`,
 *    that config is used as-is.
 *  - Otherwise the shared generic config at
 *    `build/vite.components.generic.config.ts` is used.  The component root and
 *    output directory are passed via COMPONENT_ROOT / OUT_DIR env vars.
 *
 * Vite loads both config variants via its built-in esbuild transform, so
 * TypeScript is supported in configs without a separate ts-node / tsx install.
 */

/* eslint-disable no-console */
'use strict';

const path = require('node:path');
const fs = require('node:fs');
const { execSync } = require('node:child_process');

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------
const scriptDir = __dirname;
// build/ → storefront/ → app/ → Resources/ → Storefront/ → src/ → project root
const projectRoot = path.resolve(scriptDir, '../../../../../..');
const pluginsJsonPath = path.join(projectRoot, 'var', 'plugins.json');
const genericConfigPath = path.join(scriptDir, 'vite.components.generic.config.ts');

// ---------------------------------------------------------------------------
// Load plugins.json
// ---------------------------------------------------------------------------
let plugins;
try {
    plugins = JSON.parse(fs.readFileSync(pluginsJsonPath, 'utf-8'));
} catch {
    console.error(`[build-components] Could not read ${pluginsJsonPath}. Run bin/console bundle:dump first.`);
    process.exit(1);
}

// ---------------------------------------------------------------------------
// Collect bundles that have a components directory
// ---------------------------------------------------------------------------
const bundlesWithComponents = Object.entries(plugins).filter(
    ([, config]) => config.components?.path,
);

if (bundlesWithComponents.length === 0) {
    console.log('[build-components] No bundles with components found. Nothing to build.');
    process.exit(0);
}

// ---------------------------------------------------------------------------
// Helper: run a shell command and inherit stdio
// ---------------------------------------------------------------------------
function run(cmd, opts = {}) {
    execSync(cmd, { stdio: 'inherit', ...opts });
}

// ---------------------------------------------------------------------------
// Build each bundle
// ---------------------------------------------------------------------------
for (const [bundleName, config] of bundlesWithComponents) {
    const bundleAbsPath = path.resolve(projectRoot, config.basePath);
    const storefrontAppDir = path.join(bundleAbsPath, 'Resources', 'app', 'storefront');
    const componentRoot = path.join(bundleAbsPath, config.components.path);
    const outDir = path.join(storefrontAppDir, 'dist-es', 'components');
    const customConfig = path.join(storefrontAppDir, 'vite.components.config.ts');

    console.log(`\n[build-components] Building ${bundleName}…`);
    console.log(`  components : ${componentRoot}`);
    console.log(`  output     : ${outDir}`);

    // -- npm install if the bundle has its own package.json ------------------
    if (config.components.hasPackageJson) {
        console.log(`  Running npm install in ${storefrontAppDir}`);
        run('npm install --prefer-offline', { cwd: storefrontAppDir });
    }

    const viteBin = path.join(scriptDir, '..', 'node_modules', '.bin', 'vite');
    const configPath = fs.existsSync(customConfig) ? customConfig : genericConfigPath;

    const env = {
        ...process.env,
        COMPONENT_ROOT: componentRoot,
        OUT_DIR: outDir,
        COMPONENT_NAMESPACE: bundleName,
    };

    run(`"${viteBin}" build --config "${configPath}"`, { cwd: storefrontAppDir, env });
}

console.log('\n[build-components] Done.');
