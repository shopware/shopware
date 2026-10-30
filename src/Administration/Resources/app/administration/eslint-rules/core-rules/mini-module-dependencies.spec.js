/**
 * @sw-package framework
 */

const fs = require('fs');
const { RuleTester } = require('eslint');
const rule = require('./mini-module-dependencies');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
});

// A made-up mini-module, so the rule finds the `tests/` folder it looks for on disk.
const moduleRoot = 'src/core/example-module';
const realExistsSync = fs.existsSync;

describe('mini-module-dependencies', () => {
    beforeEach(() => {
        jest.spyOn(fs, 'existsSync').mockImplementation(
            (file) => /(^|\/)src\/core\/example-module\/(index\.ts|src|tests)$/.test(file) || realExistsSync(file),
        );
    });

    ruleTester.run('mini-module-dependencies', rule, {
        valid: [
            {
                name: 'src imports its dependencies, its own files and its assets',
                filename: `${moduleRoot}/src/sw-example.js`,
                code: `
import template from './sw-example.html.twig';
import { helper } from './helper';
import { Criteria, repositoryFactoryService } from '../dependencies';
export default { template, inject: [repositoryFactoryService] };`,
            },
            {
                name: 'dependencies.ts re-exports, reads and wraps lookups in functions',
                filename: `${moduleRoot}/dependencies.ts`,
                code: `
export { default as Criteria } from 'src/core/data/criteria.data';
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
export const getApiContext = () => Shopware.Context.api;`,
            },
            {
                name: 'code outside a mini-module is not checked',
                filename: 'src/module/sw-example/index.js',
                code: "export default { inject: ['repositoryFactory'], mixins: [Shopware.Mixin.getByName('notification')] };",
            },
        ],
        invalid: [
            {
                name: 'src uses the Shopware global',
                filename: `${moduleRoot}/src/sw-example.js`,
                code: 'const { Criteria } = Shopware.Data;',
                errors: [{ messageId: 'globalInSrc' }],
            },
            {
                name: 'src imports from outside the module',
                filename: `${moduleRoot}/src/sw-example.js`,
                code: "import Criteria from 'src/core/data/criteria.data';",
                errors: [{ messageId: 'importInSrc' }],
            },
            {
                name: 'src injects a service by a written name',
                filename: `${moduleRoot}/src/sw-example.js`,
                code: "export default { inject: ['repositoryFactory'] };",
                errors: [{ messageId: 'stringName' }],
            },
            {
                name: 'dependencies.ts looks a store up by a written name',
                filename: `${moduleRoot}/dependencies.ts`,
                code: "export const useExampleStore = () => Shopware.Store.get('swExample');",
                errors: [{ messageId: 'stringName' }],
            },
            {
                name: 'dependencies.ts looks a filter up by a written name',
                filename: `${moduleRoot}/dependencies.ts`,
                code: "export const getCurrencyFilter = () => Shopware.Filter.getByName('currency');",
                errors: [{ messageId: 'stringName' }],
            },
            {
                name: 'dependencies.ts calls something when it loads',
                filename: `${moduleRoot}/dependencies.ts`,
                code: 'export const notificationMixin = Shopware.Mixin.getByName(notification);',
                errors: [{ messageId: 'callAtLoad' }],
            },
        ],
    });
});
