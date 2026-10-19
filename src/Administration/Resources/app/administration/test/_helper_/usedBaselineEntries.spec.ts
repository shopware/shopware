/**
 * @sw-package framework
 */

import { execFileSync } from 'node:child_process';
import {
    endUsedBaselineEntries,
    recordUsedBaselineEntry,
    startUsedBaselineEntries,
    usedBaselineEntries,
} from './usedBaselineEntries';

const entry = { message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' };

describe('test/_helper_/usedBaselineEntries', () => {
    beforeEach(() => {
        // Keeps the used entries of these specs away from the run's reporter.
        jest.replaceProperty(process, 'env', { ...process.env });
        endUsedBaselineEntries();
    });

    afterAll(endUsedBaselineEntries);

    it('records nothing before a start', () => {
        recordUsedBaselineEntry(entry);
        startUsedBaselineEntries();

        expect(usedBaselineEntries()).toEqual([]);
    });

    it('lists an entry recorded twice once', () => {
        startUsedBaselineEntries();

        recordUsedBaselineEntry(entry);
        recordUsedBaselineEntry(entry);

        expect(usedBaselineEntries()).toEqual([[entry.message, entry.usageSite]]);
    });

    it('lists the entries of a process started after the start, sorted', () => {
        startUsedBaselineEntries();
        recordUsedBaselineEntry(entry);

        execFileSync(
            process.execPath,
            [
                '-e',
                `require(${JSON.stringify(require.resolve('./usedBaselineEntries'))}).recordUsedBaselineEntry(${JSON.stringify(
                    { message: 'a() is deprecated.', usageSite: 'sw-page' },
                )})`,
            ],
            { env: process.env },
        );

        expect(usedBaselineEntries()).toEqual([
            ['a() is deprecated.', 'sw-page'],
            [entry.message, entry.usageSite],
        ]);
    });

    it('lists nothing after the end', () => {
        startUsedBaselineEntries();
        recordUsedBaselineEntry(entry);

        endUsedBaselineEntries();

        expect(usedBaselineEntries()).toEqual([]);
    });
});
