import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { describe, it } from 'node:test';

const readText = (path: string) => readFileSync(new URL(path, import.meta.url), 'utf8');

const read = (path: string) => JSON.parse(readFileSync(new URL(path, import.meta.url), 'utf8')) as {
    dependencies?: Record<string, string>;
};

describe('acceptance test suite pin', () => {
    it('matches the version the acceptance tests use', () => {
        // The seeding layer is TestDataService from this package. If CI resolved a different version
        // than the acceptance suite, a factory could behave one way in tests and another in a
        // screenshot run — with nothing to reveal the mismatch but a confusing image.
        const ours = read('../package.json').dependencies?.['@shopware-ag/acceptance-test-suite'];
        const theirs = read('../../../../tests/acceptance/package.json').dependencies?.[
            '@shopware-ag/acceptance-test-suite'
        ];

        assert.equal(ours, theirs, 'pin drifted from tests/acceptance/package.json');
    });
});

describe('node version', () => {
    it('satisfies the acceptance test suite engine requirement', () => {
        // The suite declares `node: 24.x || 25.x`. A workflow pinned below that installs fine
        // locally on a newer Node and then fails only on the runner, several minutes into a run that
        // has already provisioned a shop.
        const engines = JSON.parse(
            readText('../node_modules/@shopware-ag/acceptance-test-suite/package.json'),
        ).engines as { node?: string };

        const allowed = (engines.node ?? '')
            .split('||')
            .map((range) => Number.parseInt(range.trim(), 10))
            .filter((major) => !Number.isNaN(major));

        assert.ok(allowed.length > 0, 'could not parse the suite engine requirement');

        for (const workflow of ['../../../workflows/sw-screenshot.md', '../../../workflows/sw-screenshot-action-tests.yml']) {
            for (const [, pinned] of readText(workflow).matchAll(/node-version:\s*(\d+)/g)) {
                assert.ok(
                    allowed.includes(Number(pinned)),
                    `${workflow} pins node ${pinned}, outside the suite's ${engines.node}`,
                );
            }
        }
    });
});

describe('type stripping', () => {
    it('avoids syntax Node cannot strip', () => {
        // Node runs these files with --experimental-strip-types, which erases types but performs no
        // transforms. `tsc` accepts parameter properties and enums, so nothing else catches them
        // before the CLI throws ERR_UNSUPPORTED_TYPESCRIPT_SYNTAX on a runner.
        const roots = ['../lib', '../cli', '../cli/commands'];
        const offenders: string[] = [];

        for (const root of roots) {
            const dir = new URL(`${root}/`, import.meta.url);
            for (const entry of readdirSync(dir)) {
                if (!entry.endsWith('.ts')) continue;
                const source = readText(`${root}/${entry}`);
                if (/constructor\s*\([^)]*\b(private|public|protected|readonly)\s/s.test(source)) {
                    offenders.push(`${root}/${entry}: parameter property`);
                }
                if (/^\s*(export\s+)?enum\s/m.test(source)) {
                    offenders.push(`${root}/${entry}: enum`);
                }
            }
        }

        assert.deepEqual(offenders, []);
    });
});
