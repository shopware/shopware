/**
 * @sw-package framework
 */
import path from 'node:path';
import { classifyChanges } from './break-check';
import { EXPECTED_FAILURES } from './expected-failures';
import { formatFindings } from './findings';
import { fixtureTests, loadFixtureCases } from './fixture-runner';
import { generateSnapshot } from './snapshot';

describe('public API fixtures', () => {
    const cases = loadFixtureCases(path.join(__dirname, '__fixtures__'));

    fixtureTests(cases, EXPECTED_FAILURES, { generateSnapshot, classifyChanges }).forEach((test) => {
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
        expect(formatFindings(expected.findings)).toBe(expectedSummary);
    });
});
