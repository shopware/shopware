/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-vite-8-pitfalls');
const data = require('./no-vite-8-pitfalls.data');

const CJS = data.commonJsMessage;
const CONFIG = data.configMessage;
const todo = (text) => `// TODO Codemod(6.8, vite-8): ${text}`;

// An extension with its own node_modules. Packages it does not install are looked up in the Administration.
const root = fs.mkdtempSync(path.join(os.tmpdir(), 'vite-8-pitfalls-'));
const administration = path.join(root, 'AcmePlugin/src/Resources/app/administration');
const packages = {
    'cjs-lib': { main: 'index.js' },
    'no-main': {},
    '@acme/cjs': { main: 'dist/index.js', exports: { '.': { require: './dist/index.js', default: './dist/index.js' } } },
    'esm-type': { type: 'module', main: 'index.js' },
    'esm-module-field': { main: 'index.cjs', module: 'index.mjs' },
    'esm-exports': { exports: { '.': { import: './index.mjs', require: './index.cjs' } } },
    'esm-exports-mjs': { exports: './index.mjs' },
};

for (const [name, manifest] of Object.entries(packages)) {
    fs.mkdirSync(path.join(administration, 'node_modules', name), { recursive: true });
    fs.writeFileSync(path.join(administration, 'node_modules', name, 'package.json'), JSON.stringify({ name, ...manifest }));
}

const source = path.join(administration, 'src/main.js');
const viteConfig = path.join(administration, 'vite.config.mts');

describe('no-vite-8-pitfalls', () => {
    afterAll(() => {
        fs.rmSync(root, { recursive: true, force: true });
    });

    new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('rule', rule, {
        valid: [
            { name: 'ignores ESM packages by type', filename: source, code: "import lib from 'esm-type';" },
            { name: 'ignores ESM packages by module field', filename: source, code: "import lib from 'esm-module-field';" },
            { name: 'ignores ESM packages by import condition', filename: source, code: "import lib from 'esm-exports';" },
            { name: 'ignores ESM packages by .mjs exports', filename: source, code: "import lib from 'esm-exports-mjs';" },
            { name: 'ignores ESM packages of the Administration', filename: source, code: "import { defineStore } from 'pinia';\nimport mitt from 'mitt';" },
            { name: 'ignores named and namespace imports of CommonJS packages', filename: source, code: "import { get } from 'cjs-lib';\nimport * as lib from 'cjs-lib';" },
            { name: 'ignores packages that are not installed', filename: source, code: "import lib from 'acme-not-installed';" },
            {
                name: 'ignores relative, aliased, virtual and asset imports',
                filename: source,
                code: [
                    "import template from './acme.html.twig';",
                    "import service from 'src/core/service/acme';",
                    "import useStore from 'shopware:stores/acme';",
                    "import path from 'node:path';",
                    "import fs from 'fs';",
                    "import styles from 'cjs-lib/styles.css';",
                    "import raw from 'cjs-lib?raw';",
                    "import esm from 'cjs-lib/index.mjs';",
                ].join('\n'),
            },
            { name: 'ignores other files named like a config', filename: path.join(administration, 'src/vite.config.helper.js'), code: 'export default {};' },
        ],
        invalid: [
            {
                name: 'marks default imports of CommonJS-only packages of the extension',
                filename: source,
                code: [
                    "import lib from 'cjs-lib';",
                    "import scoped, { named } from '@acme/cjs';",
                    "import helper from 'cjs-lib/helper';",
                    "import other from 'no-main/other.cjs';",
                ].join('\n'),
                output: [
                    todo(CJS),
                    "import lib from 'cjs-lib';",
                    todo(CJS),
                    "import scoped, { named } from '@acme/cjs';",
                    todo(CJS),
                    "import helper from 'cjs-lib/helper';",
                    todo(CJS),
                    "import other from 'no-main/other.cjs';",
                ].join('\n'),
                errors: [{ message: CJS }, { message: CJS }, { message: CJS }, { message: CJS }],
            },
            {
                name: 'falls back to the node_modules of the Administration',
                filename: path.join(root, 'AcmeApp/Resources/app/administration/src/main.js'),
                code: "import debounce from 'lodash/debounce';",
                output: [todo(CJS), "import debounce from 'lodash/debounce';"].join('\n'),
                errors: [{ message: CJS }],
            },
            {
                name: 'marks the own Vite config at its top',
                filename: viteConfig,
                code: ['/* Vite config of the extension */', "import { defineConfig } from 'vite';", '', 'export default defineConfig({});'].join('\n'),
                output: [
                    todo(CONFIG),
                    '/* Vite config of the extension */',
                    "import { defineConfig } from 'vite';",
                    '',
                    'export default defineConfig({});',
                ].join('\n'),
                errors: [{ message: CONFIG }],
            },
            {
                name: 'reports a marked Vite config without a fix',
                filename: viteConfig,
                code: [todo(CONFIG), 'export default {};'].join('\n'),
                output: null,
                errors: [{ message: CONFIG }],
            },
        ],
    });
});
