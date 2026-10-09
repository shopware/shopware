import { readFileSync, writeFileSync } from 'node:fs';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

/** What a comparison found. */
export interface DiffResult {
    /** Pixels that differ between the two images. */
    changed: number;
    /** Total pixels compared. */
    total: number;
    /** {@link changed} as a percentage of {@link total}, rounded to two decimals. */
    percent: number;
    width: number;
    height: number;
}

/** Raised when two images cannot be compared at all, as opposed to comparing and finding nothing. */
export class DimensionMismatch extends Error {
    readonly before: { width: number; height: number };
    readonly after: { width: number; height: number };

    constructor(
        before: { width: number; height: number },
        after: { width: number; height: number },
    ) {
        super(
            `images are ${before.width}x${before.height} and ${after.width}x${after.height}; ` +
                'a heat map needs both captured at the same size and scroll position',
        );
        this.before = before;
        this.after = after;
        this.name = 'DimensionMismatch';
    }
}

/**
 * Compare two screenshots and write a heat map of the pixels that moved.
 *
 * Use it when a change is easy to miss by eye — a few pixels of padding, a shifted border — where
 * two images side by side look identical to a reviewer scrolling past.
 *
 * Throws {@link DimensionMismatch} rather than comparing images of different sizes: padding one to
 * fit the other marks every shifted pixel as changed, which reads as a catastrophic difference and
 * hides the real one.
 *
 * @example
 * const result = writeDiff('before.png', 'after.png', 'diff.png');
 * // { changed: 812, total: 288543, percent: 0.28, width: 407, height: 709 }
 */
export function writeDiff(beforePath: string, afterPath: string, outPath: string): DiffResult {
    const before = PNG.sync.read(readFileSync(beforePath));
    const after = PNG.sync.read(readFileSync(afterPath));

    if (before.width !== after.width || before.height !== after.height) {
        throw new DimensionMismatch(before, after);
    }

    const { width, height } = before;
    const diff = new PNG({ width, height });

    // Anti-aliasing shifts pixels on any re-render, so counting it would bury a real change in
    // noise. The threshold is the Playwright default.
    const changed = pixelmatch(before.data, after.data, diff.data, width, height, {
        threshold: 0.2,
        includeAA: false,
    });

    writeFileSync(outPath, PNG.sync.write(diff));

    const total = width * height;

    return { changed, total, percent: Math.round((changed / total) * 10000) / 100, width, height };
}
