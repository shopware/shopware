/**
 * @package admin
 * @private
 */

import fs from 'fs';
import { createSourceFileFilter } from '../public-api-source-files';

const POSITION_IDENTIFIER_REGEX = /position-identifier="(.+)"/gm;

// A native setup SFC keeps its markup in the `.vue` template instead of a `.html.twig`, so a
// twig-only scan reports every position identifier of a converted component as removed public API.
export const isPositionIdentifierSourceFile = createSourceFileFilter(/^.*\.(html\.twig|vue)$/);

export function extractPositionIdentifiers(filesPath: string[]): string[] {
    return filesPath.flatMap((filePath) => {
        const code = fs.readFileSync(filePath, { encoding: 'utf-8' });

        return (
            [...code.matchAll(POSITION_IDENTIFIER_REGEX)]
                .map((match) => match[1])
                // An empty or `null` value is a placeholder, not a registered extension point. The
                // filter lives here rather than in the guard alone so the generator cannot write an
                // identifier into the tracked list that the guard afterwards refuses to see.
                .filter((identifier) => identifier !== '' && identifier !== 'null')
        );
    });
}
