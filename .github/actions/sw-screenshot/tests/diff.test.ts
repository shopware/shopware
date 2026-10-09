import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, it } from 'node:test';
import { PNG } from 'pngjs';
import { DimensionMismatch, writeDiff } from '../lib/diff.ts';

const dir = mkdtempSync(join(tmpdir(), 'sw-shot-diff-'));

/** Write a solid PNG with an optional filled rectangle, so tests can control the pixel delta. */
function png(name: string, width: number, height: number, box?: { x: number; y: number; w: number; h: number }): string {
    const image = new PNG({ width, height });

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = (width * y + x) << 2;
            const inBox = box && x >= box.x && x < box.x + box.w && y >= box.y && y < box.y + box.h;
            image.data[i] = inBox ? 0 : 255;
            image.data[i + 1] = inBox ? 0 : 255;
            image.data[i + 2] = inBox ? 0 : 255;
            image.data[i + 3] = 255;
        }
    }

    const path = join(dir, name);
    writeFileSync(path, PNG.sync.write(image));

    return path;
}

describe('writeDiff', () => {
    it('reports nothing changed for identical images', () => {
        // The useful case: a pull request that claims a visual change but produces none.
        const a = png('same-a.png', 40, 30, { x: 5, y: 5, w: 10, h: 10 });
        const b = png('same-b.png', 40, 30, { x: 5, y: 5, w: 10, h: 10 });

        const result = writeDiff(a, b, join(dir, 'same-diff.png'));

        assert.equal(result.changed, 0);
        assert.equal(result.percent, 0);
        assert.equal(result.total, 1200);
    });

    it('counts only the pixels that moved', () => {
        const a = png('shift-a.png', 40, 30, { x: 5, y: 5, w: 10, h: 10 });
        const b = png('shift-b.png', 40, 30, { x: 5, y: 5, w: 20, h: 10 });

        const result = writeDiff(a, b, join(dir, 'shift-diff.png'));

        // The box widened by 10 columns over 10 rows; nothing else moved.
        assert.equal(result.changed, 100);
        assert.ok(result.percent > 8 && result.percent < 9, `unexpected percent ${result.percent}`);
    });

    it('writes a heat map the same size as its inputs', () => {
        const a = png('out-a.png', 40, 30, { x: 1, y: 1, w: 4, h: 4 });
        const b = png('out-b.png', 40, 30);
        const out = join(dir, 'out-diff.png');

        writeDiff(a, b, out);
        const written = PNG.sync.read(readFileSync(out));

        assert.equal(written.width, 40);
        assert.equal(written.height, 30);
    });

    it('refuses images of different sizes instead of comparing them', () => {
        // Padding one to fit the other marks every shifted pixel as changed, which reads as a
        // catastrophic difference and hides the real one.
        const a = png('dim-a.png', 40, 30);
        const b = png('dim-b.png', 40, 31);

        assert.throws(() => writeDiff(a, b, join(dir, 'dim-diff.png')), DimensionMismatch);
        assert.throws(() => writeDiff(a, b, join(dir, 'dim-diff.png')), /same size and scroll position/);
    });
});
