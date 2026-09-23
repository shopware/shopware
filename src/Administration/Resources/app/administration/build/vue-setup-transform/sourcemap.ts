/**
 * @sw-package framework
 */

import type MagicString from 'magic-string';
import { SourceMap, type SourceMapSegment } from 'magic-string';

const IDENTIFIER_PATH = /[\p{ID_Continue}$.]+/uy;

/**
 * Builds the sourcemap and pins every run of generated code with unmapped segments at both ends.
 *
 * MagicString leaves inserted code without segments, so a consumer resolving a generated column with
 * either lookup bias would land on the neighbouring author code. With `hires` every original character
 * has its own segment, so any column not covered by one is generated. A segment carrying a name is a
 * renamed identifier and covers the whole replacement.
 */
function buildSourceMap(s: MagicString, filename: string): SourceMap {
    const decoded = s.generateDecodedMap({ source: filename, includeContent: true, hires: true });
    const lines = s.toString().split('\n');

    return new SourceMap({
        ...decoded,
        sources: [filename],
        sourcesContent: [s.original],
        mappings: lines.map((text, line) => pinGeneratedRuns(text, decoded.mappings[line] ?? [])),
    });
}

function pinGeneratedRuns(text: string, segments: SourceMapSegment[]): SourceMapSegment[] {
    const pinned: SourceMapSegment[] = [];
    let covered = 0;
    const pin = (end: number) => {
        if (end > covered) {
            pinned.push([covered]);

            if (end - 1 > covered) {
                pinned.push([end - 1]);
            }
        }
    };

    segments.forEach((segment) => {
        pin(segment[0]);
        pinned.push(segment);
        IDENTIFIER_PATH.lastIndex = segment[0];
        covered = segment[0] + (segment.length === 5 ? (IDENTIFIER_PATH.exec(text)?.[0].length ?? 1) : 1);
    });
    pin(text.length);

    return pinned;
}

/**
 * @private
 */
export { buildSourceMap };
