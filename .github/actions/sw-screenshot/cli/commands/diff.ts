import { DimensionMismatch, writeDiff } from '../../lib/diff.ts';

/**
 * Write a heat map of what changed between two screenshots.
 *
 * Offered, not required — reach for it when the change is subtle enough that two images side by side
 * would not show it. Both screenshots must be captured the same way: same viewport, same element,
 * same scroll position.
 *
 * @example
 * // shot diff before.png after.png diff.png
 * // 812 of 288543 pixels changed (0.28%)
 */
export function diff(argv: string[]): void {
    const [before, after, out] = argv;

    if (!before || !after) {
        throw new Error('usage: shot diff <before.png> <after.png> [out.png]');
    }

    try {
        const result = writeDiff(before, after, out || 'diff.png');

        if (result.changed === 0) {
            process.stdout.write('0 pixels changed — the two screenshots are identical\n');

            return;
        }

        process.stdout.write(
            `${result.changed} of ${result.total} pixels changed (${result.percent}%)\n`,
        );
    } catch (error) {
        if (error instanceof DimensionMismatch) {
            throw new Error(`cannot compare: ${error.message}`);
        }

        throw error;
    }
}
