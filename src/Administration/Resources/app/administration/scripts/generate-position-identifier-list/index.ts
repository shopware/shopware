/**
 * @package admin
 * @private
 */

import fs from 'fs';
import path from 'path';
import cliProgress from 'cli-progress';
import colors from 'picocolors';
import { globSync } from 'glob';
import { extractPositionIdentifiers } from './extract-position-identifiers';
import { isTemplateSourceFile } from '../public-api-source-files';

const templateFiles = globSync(`${path.join(__dirname, '../../src')}/**/*.*`).filter(isTemplateSourceFile);

console.log(colors.blue('Gathering position identifiers...\n'));

// Create and start a progress bar
const pb = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
pb.start(templateFiles.length, 0);

const result = templateFiles.flatMap((file) => {
    pb.increment();

    return extractPositionIdentifiers(fs.readFileSync(file, { encoding: 'utf-8' }));
});

// Stop the progress bar
pb.stop();

// Sort the result array to maintain consistent ordering
const sortedPositionIdentifiers = result.sort((a, b) => a.localeCompare(b));

// Define the output file path for the result
const outputFile = path.join(__dirname, '../../src/meta/position-identifiers.json');

console.log(colors.blueBright(`\nWriting to ${outputFile}`));

fs.writeFileSync(outputFile, JSON.stringify(sortedPositionIdentifiers, null, 1));

console.log(colors.green('\nAll done!'));
