/**
 * @sw-package framework
 *
 * Integration against the REAL Administration, not a stub: is the type surface an
 * extension sees actually alive?
 *
 * The stub-Administration specs cannot answer that. `admin-types.d.ts` pulls the
 * Administration's own sources into every extension program, and those sources
 * import each other as `src/…`. When that does not resolve, `global.types.ts`
 * cannot resolve `ShopwareClass`, so the global `Shopware` is declared as the
 * *error type* — which absorbs every property access without complaint. The
 * result is a type check that passes while checking nothing, and the only visible
 * symptom is ESLint's `no-unsafe-*` rules on `Shopware`.
 *
 * These specs are therefore written as assertions about `Shopware` itself.
 */

import fs from 'fs';
import path from 'path';
import { runCheck } from '../runner';
import { REAL_ADMINISTRATION_ROOT, createExtension, createTempProject, removeTempProject } from '../test-helpers';
import type { AdminRoot, CheckReport, ToolRun } from '../shared';

jest.setTimeout(300_000);

const PROBES_THE_HOST_TYPES = `Shopware.Module.register('swag-example', {});
Shopware.definitelyNotAThing;
`;

describe('scripts/extensionTooling type surface (integration, real Administration)', () => {
    let projectRoot: string;
    let report: CheckReport;
    let types: ToolRun;
    let lint: ToolRun;

    beforeAll(() => {
        projectRoot = createTempProject();

        const extension = createExtension(projectRoot, {
            name: 'SwagTypeSurface',
            sources: { 'module/swag-example/index.ts': PROBES_THE_HOST_TYPES },
        });
        const sourcePath = path.join(projectRoot, extension.basePath, 'Resources/app/administration/src');
        const root: AdminRoot = {
            bundleName: 'SwagTypeSurface',
            technicalName: 'swag-type-surface',
            extensionName: 'SwagTypeSurface',
            extensionRoot: path.join(projectRoot, 'custom/plugins/SwagTypeSurface'),
            sourcePath,
            adminFolder: path.dirname(sourcePath),
            slug: 'swag-type-surface',
            platform: false,
        };

        report = runCheck({
            projectRoot,
            administrationRoot: REAL_ADMINISTRATION_ROOT,
            roots: [root],
            types: true,
            lint: true,
            fix: false,
        });

        types = report.roots[0].runs.find((run) => run.tool === 'types') as ToolRun;
        lint = report.roots[0].runs.find((run) => run.tool === 'lint') as ToolRun;
    });

    afterAll(() => {
        removeTempProject(projectRoot);
    });

    it('resolves every module of the host sources', () => {
        expect(types.unresolvedHostModules).toBe(0);
    });

    it('types the global Shopware object instead of leaving it an error type', () => {
        // If `Shopware` were the error type, this access would produce no
        // diagnostic at all — silence here means the type surface is dead.
        expect(types.findings).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    rule: 'TS2339',
                    message: expect.stringContaining("Property 'definitelyNotAThing' does not exist"),
                }),
            ]),
        );
    });

    it('type-checks a call against the host signature', () => {
        expect(types.findings.map((finding) => finding.message).join('\n')).toContain('ModuleManifest');
    });

    it('does not report the host object as unresolvable to the type-aware lint rules', () => {
        const unsafe = lint.findings.filter((finding) => finding.rule?.startsWith('@typescript-eslint/no-unsafe-'));

        expect(unsafe).toEqual([]);
    });

    it('reports no tool error', () => {
        expect(types.errors).toEqual([]);
        expect(lint.errors).toEqual([]);
    });

    it('maps the host resolution of the installed Administration into the generated program', () => {
        const generated = JSON.parse(
            fs.readFileSync(path.join(projectRoot, 'var/admin-extension-tooling/swag-type-surface/tsconfig.json'), 'utf8'),
        ) as { compilerOptions: { paths: Record<string, string[]> } };

        expect(generated.compilerOptions.paths['*']).toEqual([
            `${REAL_ADMINISTRATION_ROOT}/*`,
            `${REAL_ADMINISTRATION_ROOT}/node_modules/*`,
        ]);
        // Taken from the Administration's own tsconfig, never from a list kept here.
        expect(generated.compilerOptions.paths.src).toEqual([`${REAL_ADMINISTRATION_ROOT}/src`]);
    });
});
