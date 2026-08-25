/**
 * @package admin
 * @private
 */

import { captures } from '../public-api-source-files';

// May the regex god be with us: https://regex101.com/r/BM083Q/1
const DATA_SET_ID_REGEX = /\.publishData\(\{[^}]*?\bid\s*:\s*['"]([^'"]+)['"]/gm;

export function extractDataSetIds(code: string): string[] {
    return captures(code, DATA_SET_ID_REGEX);
}
