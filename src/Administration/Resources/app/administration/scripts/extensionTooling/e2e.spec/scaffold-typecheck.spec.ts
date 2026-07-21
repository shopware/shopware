/**
 * @sw-package framework
 *
 * Guards the 4.3 acceptance criterion: a freshly scaffolded TypeScript
 * Administration module (the exact `ts-module.stub` that `plugin:create
 * --create-admin-module` ships) passes the tooling with no manual repair. Reads
 * the real stub so a future edit that breaks type-checking fails here.
 */

import fs from 'fs';
import path from 'path';
import { checkExtensions } from '../check';
import {
    cleanupTempProject,
    createTempProject,
    createVendorAdmin,
    realAdministrationRoot,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

const CHECK_TIMEOUT = 300000;
const TS_MODULE_STUB = path.resolve(
    realAdministrationRoot,
    '../../../../..',
    'src/Core/Framework/Plugin/Command/Scaffolding/stubs/ts-module.stub',
);

describe('extension tooling scaffolded TypeScript module (e2e)', () => {
    it(
        'type-checks and lints the shipped ts-module scaffold without manual fixes',
        async () => {
            const projectRoot = createTempProject('sw-tooling-scaffold-ts-');
            const administrationRoot = createVendorAdmin(projectRoot, { entitySchema: 'real' });
            const sourceRoot = path.join(projectRoot, 'custom/plugins/SwagScaffold/src/Resources/app/administration/src');

            try {
                expect(fs.existsSync(TS_MODULE_STUB)).toBe(true);

                writeFile(path.join(projectRoot, 'custom/plugins/SwagScaffold/composer.json'), '{}\n');
                writeFile(path.join(sourceRoot, 'main.ts'), [
                    "import './module/swag-example';",
                    '',
                ]);
                writeFile(path.join(sourceRoot, 'module/swag-example/index.ts'), fs.readFileSync(TS_MODULE_STUB, 'utf8'));

                writePluginsConfig(projectRoot, [
                    {
                        technicalName: 'SwagScaffold',
                        basePath: 'custom/plugins/SwagScaffold/src',
                        administrationPath: 'Resources/app/administration/src',
                    },
                ]);

                const check = await checkExtensions({ projectRoot, administrationRoot, only: 'SwagScaffold' });
                const result = check.results[0];

                expect(result.typescript.status).toBe('passed');
                expect(result.eslint.status).toBe('passed');
                expect(check.exitCode).toBe(0);
            } finally {
                cleanupTempProject(projectRoot);
            }
        },
        CHECK_TIMEOUT,
    );
});
