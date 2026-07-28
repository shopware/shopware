/**
 * @sw-package framework
 *
 * picocolors decides color support once at import time and env.CI enables it,
 * so GitHub Actions renders colored reports while local jest output stays
 * plain. These tests force both modes through a fresh module registry and pin
 * that text spanning adjacent colored spans (green+dim, dim+dim, dim+cyan)
 * stays assertable once ANSI sequences are stripped.
 */

import type * as reportModuleType from '../report';
import { extension, project, run, setupResult, stripAnsi } from './helpers';

type ReportModule = typeof reportModuleType;

function renderWith(env: Record<string, string | undefined>, render: (reportModule: ReportModule) => string): string {
    const saved = Object.fromEntries(
        Object.keys(env).map((key) => [
            key,
            process.env[key],
        ]),
    );
    const apply = (values: Record<string, string | undefined>) => {
        for (const [
            key,
            value,
        ] of Object.entries(values)) {
            if (value === undefined) {
                delete process.env[key];
            } else {
                process.env[key] = value;
            }
        }
    };
    let output = '';

    apply(env);
    try {
        jest.isolateModules(() => {
            // eslint-disable-next-line @typescript-eslint/no-require-imports
            output = render(require('../report') as ReportModule);
        });
    } finally {
        apply(saved);
    }

    return output;
}

const FORCED_ON = { FORCE_COLOR: '1', NO_COLOR: undefined };
const FORCED_OFF = { FORCE_COLOR: undefined, NO_COLOR: '1' };

const renderVacuousPass = (reportModule: ReportModule) =>
    reportModule.renderCheckReport({
        results: [extension(project('JsOnly'), { typescript: run('no-files', { durationMs: 0 }) })],
        fatalDiagnostics: [],
        warnings: [],
        baselineUpdates: [],
        exitCode: 0,
    });

const renderDryRun = (reportModule: ReportModule) =>
    reportModule.renderSetupReport(
        setupResult([project('Mono')], {
            changed: true,
            writes: [
                { file: 'custom/plugins/Mono/.shopware-admin/tsconfig.json', state: 'created' },
                { file: 'custom/plugins/Mono/tsconfig.json', state: 'created' },
                { file: 'var/admin-extension-tooling/manifest.json', state: 'created' },
            ],
        }),
        { checkOnly: true },
    );

describe('scripts/extensionTooling/report color rendering', () => {
    it('emits ANSI and keeps the vacuous-pass qualifier assertable when color is forced on', () => {
        const colored = renderWith(FORCED_ON, renderVacuousPass);

        expect(stripAnsi(colored)).not.toBe(colored);
        expect(stripAnsi(colored)).toContain('✔ passed (0 TypeScript files — .js is not type-checked)');
    });

    it('keeps dry-run ownership labels assertable when color is forced on', () => {
        const colored = renderWith(FORCED_ON, renderDryRun);
        const output = stripAnsi(colored);

        expect(output).not.toBe(colored);
        expect(output).toContain('would create: custom/plugins/Mono/.shopware-admin/tsconfig.json [git-ignored bridge]');
        expect(output).toContain('would create: custom/plugins/Mono/tsconfig.json [commit this]');
    });

    it('stripping the colored report reproduces the plain rendering', () => {
        expect(stripAnsi(renderWith(FORCED_ON, renderVacuousPass))).toBe(renderWith(FORCED_OFF, renderVacuousPass));
        expect(stripAnsi(renderWith(FORCED_ON, renderDryRun))).toBe(renderWith(FORCED_OFF, renderDryRun));
    });
});
