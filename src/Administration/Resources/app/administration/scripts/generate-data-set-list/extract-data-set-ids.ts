/**
 * @package admin
 * @private
 */

import fs from 'fs';
import { createSourceFileFilter } from '../public-api-source-files';

// May the regex god be with us: https://regex101.com/r/BM083Q/1
const DATA_SET_ID_REGEX = /\.publishData\(\{[^}]*?\bid\s*:\s*['"]([^'"]+)['"]/gm;

// The SFC codemod moves `.publishData(` into `<script setup>`, so a `.js`/`.ts`-only scan reports
// every data set of a converted component as removed public API. The lookbehinds sit before the
// extension alternation, so `foo.spec.vue` and `foo.vue2.ts` are excluded too.
export const isDataSetSourceFile = createSourceFileFilter(/^.*(?<!\.spec|vue2)(?<!\/acl\/index)(?<!\.d)\.(js|ts|vue)$/);

export function extractDataSetIds(filesPath: string[]): string[] {
    return filesPath.flatMap((filePath) => {
        const code = fs.readFileSync(filePath, { encoding: 'utf-8' });

        return [...code.matchAll(DATA_SET_ID_REGEX)].map((match) => match[1]);
    });
}
