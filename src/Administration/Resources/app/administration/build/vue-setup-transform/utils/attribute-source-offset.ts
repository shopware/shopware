/**
 * @sw-package framework
 */

// eslint-disable-next-line import/extensions -- The package exports this subpath only with its .js suffix.
import { DecodingMode, EntityDecoder, htmlDecodeTree } from 'entities/lib/decode.js';

/**
 * Maps a Babel offset in a decoded directive value back to Vue's raw attribute source.
 * Use the same HTML attribute decoding rules as Vue, including semicolon-less entities.
 */
export function attributeSourceOffset(source: string, offset: number): number {
    if (!source.includes('&')) {
        return offset;
    }

    let rawOffset = 0;
    let decodedOffset = 0;
    let decodedLength = 0;
    const decoder = new EntityDecoder(htmlDecodeTree, (codepoint) => {
        decodedLength += String.fromCodePoint(codepoint).length;
    });

    while (rawOffset < source.length && decodedOffset < offset) {
        let consumed = 1;
        decodedLength = 1;

        if (source[rawOffset] === '&') {
            decodedLength = 0;
            decoder.startEntity(DecodingMode.Attribute);
            consumed = decoder.write(source, rawOffset + 1);

            if (consumed < 0) {
                consumed = decoder.end();
            }

            if (consumed === 0) {
                consumed = 1;
                decodedLength = 1;
            }
        }

        // Every code unit produced by an entity points to its opening ampersand.
        if (decodedOffset + decodedLength > offset) {
            break;
        }

        rawOffset += consumed;
        decodedOffset += decodedLength;
    }

    return rawOffset;
}
