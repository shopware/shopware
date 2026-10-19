/**
 * @sw-package framework
 */

import { execFileSync } from 'node:child_process';
import {
    recordUsedBaselineEntry,
    startCollectingUsedBaselineEntries,
    stopCollectingUsedBaselineEntries,
    usedBaselineEntries,
} from './usedBaselineEntries';

const entry = { message: 'oldFunction() is deprecated.', usageSite: 'src/app/main.ts' };

describe('test/_helper_/usedBaselineEntries', () => {
    beforeEach(() => {
        // Keeps the used entries of these specs away from the run's reporter.
        jest.replaceProperty(process, 'env', { ...process.env });
        stopCollectingUsedBaselineEntries();
    });

    afterAll(stopCollectingUsedBaselineEntries);

    it('records nothing before collecting starts', () => {
        recordUsedBaselineEntry(entry);
        startCollectingUsedBaselineEntries();

        expect(usedBaselineEntries()).toEqual([]);
    });

    it('lists an entry recorded twice once', () => {
        startCollectingUsedBaselineEntries();

        recordUsedBaselineEntry(entry);
        recordUsedBaselineEntry(entry);

        expect(usedBaselineEntries()).toEqual([[entry.message, entry.usageSite]]);
    });

    it('lists the entries of a process started after collecting started, sorted', () => {
        startCollectingUsedBaselineEntries();
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

    it('lists nothing after collecting stops', () => {
        startCollectingUsedBaselineEntries();
        recordUsedBaselineEntry(entry);

        stopCollectingUsedBaselineEntries();

        expect(usedBaselineEntries()).toEqual([]);
    });
});
