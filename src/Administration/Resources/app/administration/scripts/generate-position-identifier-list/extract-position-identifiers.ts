/**
 * @package admin
 * @private
 */

import { captures } from '../public-api-source-files';

const POSITION_IDENTIFIER_REGEX = /position-identifier="(.+)"/gm;

export function extractPositionIdentifiers(code: string): string[] {
    // An empty or `null` value is a placeholder, not a registered extension point. Filtering here
    // rather than in the guard alone keeps the generator from writing an identifier into the tracked
    // list that the guard afterwards refuses to see.
    return captures(code, POSITION_IDENTIFIER_REGEX).filter(
        (identifier) => identifier !== '' && identifier !== 'null',
    );
}
