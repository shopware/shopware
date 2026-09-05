/**
 * @sw-package framework
 */

import fs from 'fs';
import path from 'path';

const ADMIN_PREFIX = 'src/Administration/Resources/app/administration';

/**
 * Rewrites the cobertura report in place: class filenames become repo-root relative and the
 * <sources> element collapses to the repository root. Needed because GitLab has no support
 * for resolving <sources> entries.
 *
 * A plain string transform instead of an XML parse/rebuild: the report is tens of MB and
 * <class> is the only cobertura element carrying a filename attribute.
 */
module.exports = async function testTeardown(globalConfig) {
    if (!globalConfig.collectCoverage || !globalConfig.coverageReporters.includes('cobertura')) {
        return;
    }

    const cobertureFilePath = path.join(globalConfig.coverageDirectory, 'cobertura-coverage.xml');

    if (!fs.existsSync(cobertureFilePath)) {
        return;
    }

    const xml = fs.readFileSync(cobertureFilePath, 'utf8');

    const rewritten = xml
        .replace(/filename="([^"]+)"/g, (match, filename) => {
            return filename.startsWith(ADMIN_PREFIX) ? match : `filename="${ADMIN_PREFIX}/${filename}"`;
        })
        .replace(/<sources>[\s\S]*?<\/sources>/, '<sources><source>.</source></sources>');

    fs.writeFileSync(cobertureFilePath, rewritten);
};
