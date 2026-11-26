/**
 * @sw-package framework
 */
import path from 'node:path';
import { classifyChanges } from '../break-check/break-check';
import { printFindings } from '../break-check/print-findings';
import { generateSnapshot } from '../snapshot/snapshot';
import type { Snapshot } from '../snapshot/snapshot';
import { EXPECTED_FAILURES } from './expected-failures';
import { fixtureTests, loadFixtureCases } from './fixture-runner';
import type { PublicApiTools } from './fixture-runner';

describe('public API fixtures', () => {
    const cases = loadFixtureCases(path.join(__dirname, '__fixtures__'));

    const tools: PublicApiTools<Snapshot> = {
        generateSnapshot,
        classifyChanges: (base, head) => classifyChanges(base.model, head.model),
    };

    fixtureTests(cases, EXPECTED_FAILURES, tools).forEach((test) => {
        if (test.expectedToFail) {
            it.failing(`${test.name} is missing (remove it from expected-failures.ts once it passes)`, () => {
                expect(test.actual()).toEqual(test.expected);
            });
        } else {
            it(`${test.name} matches the expected output`, () => {
                expect(test.actual()).toEqual(test.expected);
            });
        }
    });

    it.each(cases)('$name prints the findings of findings.json as findings.txt', ({ expected, expectedSummary }) => {
        expect(printFindings(expected.findings)).toBe(expectedSummary);
    });
});
