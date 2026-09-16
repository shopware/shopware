/**
 * @sw-package framework
 */

/**
 * Helpers shared by the codemod specs: throwaway component trees, and the one way a
 * `__fixtures__/<name>/` directory reaches the conversion pipeline. Kept out of the specs so they
 * agree on what "the tree before and after" means and on how a fixture is converted.
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import { parse } from '@babel/parser';
import { convertComponent, type ConvertResult } from './convert-component';

const FIXTURES = path.join(__dirname, '__fixtures__');

function makeRoot(prefix: string): string {
    return fs.mkdtempSync(path.join(os.tmpdir(), prefix));
}

function writeFile(root: string, relativePath: string, contents: string | Buffer): string {
    const file = path.join(root, relativePath);

    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, contents);

    return file;
}

/** Every file below `root`, by relative path, so a run can be proven byte-for-byte non-destructive. */
function manifest(root: string): Record<string, Buffer> {
    const files: string[] = [];
    const visit = (directory: string): void => {
        fs.readdirSync(directory, { withFileTypes: true }).forEach((entry) => {
            const file = path.join(directory, entry.name);

            if (entry.isDirectory()) {
                visit(file);
                return;
            }

            files.push(file);
        });
    };

    visit(root);

    return Object.fromEntries(
        files.sort().map((file) => [
            path.relative(root, file).split(path.sep).join('/'),
            fs.readFileSync(file),
        ]),
    );
}

function fixtureNames(): string[] {
    return fs
        .readdirSync(FIXTURES)
        .filter((entry) => fs.statSync(path.join(FIXTURES, entry)).isDirectory())
        .sort();
}

/** The Twig import range a production run takes from the source model, read off the spec's own parse. */
function templateImportRange(jsSource: string): { start: number; end: number } {
    const templateImport = parse(jsSource, { sourceType: 'module', plugins: ['typescript'] }).program.body.find(
        (statement) => statement.type === 'ImportDeclaration' && statement.source.value.endsWith('.html.twig'),
    );

    if (!templateImport) {
        throw new Error('Source has no Twig import');
    }

    return { start: templateImport.start as number, end: templateImport.end as number };
}

/** The authored script of a fixture, exactly as the pipeline reads it. */
function fixtureScript(name: string): { source: string; lang: 'js' | 'ts' } {
    const dir = path.join(FIXTURES, name);
    const indexPath = [
        path.join(dir, 'index.js'),
        path.join(dir, 'index.ts'),
    ].find((file) => fs.existsSync(file)) as string;

    return { source: fs.readFileSync(indexPath, 'utf8'), lang: indexPath.endsWith('.ts') ? 'ts' : 'js' };
}

async function convertFixture(name: string): Promise<ConvertResult> {
    const dir = path.join(FIXTURES, name);
    const { source: jsSource, lang } = fixtureScript(name);

    return convertComponent({
        jsSource,
        twigSource: fs.readFileSync(path.join(dir, `${name}.html.twig`), 'utf8'),
        componentName: name,
        vuePath: path.join(dir, `${name}.vue`),
        lang,
        templateImportRange: templateImportRange(jsSource),
    });
}

export { FIXTURES, convertFixture, fixtureNames, fixtureScript, makeRoot, manifest, templateImportRange, writeFile };
