/**
 * @sw-package framework
 *
 * picocolors decides color support once at import time and env.CI enables it,
 * so GitHub Actions renders colored reports while local jest output stays
 * plain. This pins that the other specs' stripAnsi assertions survive that:
 * text spanning adjacent colored spans stays assertable once the ANSI
 * sequences are stripped.
 */

import type * as reportModuleType from '../report';
import { owned, project, setupResult, stripAnsi } from './helpers';

type ReportModule = typeof reportModuleType;

describe('scripts/extensionTooling/report color rendering', () => {
    it('keeps the report assertable once ANSI is stripped when color is forced on', () => {
        const savedForce = process.env.FORCE_COLOR;
        const savedNo = process.env.NO_COLOR;
        let colored = '';

        process.env.FORCE_COLOR = '1';
        delete process.env.NO_COLOR;

        try {
            jest.isolateModules(() => {
                // eslint-disable-next-line @typescript-eslint/no-require-imports
                const reportModule = require('../report') as ReportModule;

                colored = reportModule.renderSetupReport(
                    setupResult(
                        [
                            project('Ready'),
                            project('NeedsBridge', {
                                tsconfig: owned('custom/plugins/NeedsBridge/src/tsconfig.json', 'drift'),
                            }),
                        ],
                        {
                            changed: true,
                            writes: [
                                { file: 'custom/plugins/Mono/.shopware/tsconfig.json', state: 'created' },
                                { file: 'custom/plugins/Mono/tsconfig.json', state: 'created' },
                            ],
                        },
                    ),
                    { checkOnly: true },
                );
            });
        } finally {
            process.env.FORCE_COLOR = savedForce;
            process.env.NO_COLOR = savedNo;
        }

        const output = stripAnsi(colored);

        expect(output).not.toBe(colored);
        expect(output).toContain('✔ ready  Ready');
        expect(output).toContain('⚠ not bridged  NeedsBridge');
        expect(output).toContain('would create: custom/plugins/Mono/tsconfig.json [commit this]');
    });
});
