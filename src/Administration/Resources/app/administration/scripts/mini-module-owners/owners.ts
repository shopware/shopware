/**
 * @sw-package framework
 * @private
 *
 * The code owners of the mini-modules. `mini-module-owners.json` is the only place that names them;
 * the mini-module block of the repository's `.codeowners` is generated from it.
 */

import fs from 'node:fs';
import path from 'node:path';

const adminRoot = path.resolve(__dirname, '../..');

/** @private The manifest: mini-module directory, relative to the Administration root, to its owner. */
export const MANIFEST_FILE = path.join(adminRoot, 'mini-module-owners.json');

/** @private The repository's code owners file, which holds the generated block. */
export const CODEOWNERS_FILE = path.resolve(adminRoot, '../../../../..', '.codeowners');

/** @private The command that rewrites the generated block from the manifest. */
export const REGENERATE_OWNERS_COMMAND = 'composer admin:generate-mini-module-owners';

/** Where the Administration root lies in the repository, which `.codeowners` patterns start from. */
const ADMIN_PATH = 'src/Administration/Resources/app/administration';

const BLOCK_START = `# mini-modules: generated from ${ADMIN_PATH}/mini-module-owners.json by \`${REGENERATE_OWNERS_COMMAND}\``;
const BLOCK_END = '# end of the generated mini-modules';

/**
 * The files of a mini-module whose changes need its owner: the public API and the tests that state decisions.
 * `src/` and the pinned tests need no owner. Patterns may name files that do not exist yet.
 */
const OWNED_FILES = [
    'index.ts',
    'global.d.ts',
    'tests/requirements.spec.ts',
    'tests/regressions.spec.ts',
    'tests/public-api.spec.ts',
];

/** Files of `OWNED_FILES` that are listed only when the module has them, because most modules never will. */
const LISTED_IF_PRESENT = ['global.d.ts'];

/**
 * The owner of a mini-module as `.codeowners` writes it: the team, with `? ` in front when it is only optional.
 *
 * @example
 * '@shopware/product-cc-checkout'      // required: the team must approve
 * '? @shopware/product-cc-discovery'   // optional: the team is pinged, but does not block the merge
 *
 * @private
 */
export type Owner = string;

/** @private Mini-module directory, relative to the Administration root, to its owner. */
export type OwnersManifest = Record<string, Owner>;

/** @private Reads the manifest. */
export function readManifest(): OwnersManifest {
    return JSON.parse(fs.readFileSync(MANIFEST_FILE, 'utf8')) as OwnersManifest;
}

/** @private Writes the manifest with its directories sorted, so an addition lands in a stable place. */
export function writeManifest(manifest: OwnersManifest): void {
    const sorted = Object.fromEntries(
        Object.keys(manifest)
            .sort()
            .map((directory) => [
                directory,
                manifest[directory],
            ]),
    );

    fs.writeFileSync(MANIFEST_FILE, `${JSON.stringify(sorted, null, 4)}\n`);
}

/**
 * The generated block of `.codeowners`: one line per owned file of every module in the manifest.
 * `hasFile` tells whether a module has a file that is listed only when present.
 *
 * @example
 * renderOwnersBlock({ 'src/module/sw-x': '? @shopware/team' }, () => false)
 * // → "… \n? src/Administration/…/src/module/sw-x/index.ts @shopware/team\n…"
 *
 * @private
 */
export function renderOwnersBlock(manifest: OwnersManifest, hasFile: (file: string) => boolean): string {
    const lines = Object.keys(manifest)
        .sort()
        .flatMap((directory) => {
            const [prefix, team] = manifest[directory].startsWith('? ')
                ? ['? ', manifest[directory].slice(2)]
                : ['', manifest[directory]];

            return OWNED_FILES.filter((file) => !LISTED_IF_PRESENT.includes(file) || hasFile(`${directory}/${file}`)).map(
                (file) => `${prefix}${ADMIN_PATH}/${directory}/${file} ${team}`,
            );
        });

    return [
        BLOCK_START,
        '# A change to the public API (`index.ts`), the requirements, the regressions or the public-api tests needs the owning team.',
        '# Changes to `src/` and to pinned tests do not.',
        ...lines,
        BLOCK_END,
    ].join('\n');
}

/**
 * The `.codeowners` text with its generated block replaced, or appended after an empty line when it has none.
 *
 * @private
 */
export function replaceOwnersBlock(codeowners: string, block: string): string {
    const start = codeowners.indexOf(BLOCK_START);
    if (start === -1) {
        return `${codeowners.trimEnd()}\n\n${block}\n`;
    }

    const end = codeowners.indexOf(BLOCK_END, start) + BLOCK_END.length;
    return `${codeowners.slice(0, start)}${block}${codeowners.slice(end)}`;
}

/** @private The `.codeowners` text as the manifest wants it, read from disk. */
export function expectedCodeowners(): string {
    const block = renderOwnersBlock(readManifest(), (file) => fs.existsSync(path.join(adminRoot, file)));

    return replaceOwnersBlock(fs.readFileSync(CODEOWNERS_FILE, 'utf8'), block);
}

/**
 * A `.codeowners` pattern as a regular expression for paths relative to the repository root:
 * `**` matches across directories, `*` and `?` within one, a trailing `/` everything below.
 */
function patternToRegExp(pattern: string): RegExp {
    const source = (pattern.endsWith('/') ? `${pattern}**` : pattern)
        .split(/(\*\*|\*|\?)/)
        .map((part) => ({ '**': '.*', '*': '[^/]*', '?': '[^/]' })[part] ?? part.replace(/[.+^${}()|[\]\\]/g, '\\$&'))
        .join('');

    return new RegExp(`^${source}$`);
}

/**
 * What `.codeowners` already says about a mini-module directory, outside the generated block:
 * the owner of the last line that matches a file in it, which is the line that wins.
 * Returns null when no line matches or the winning line names more than one team, so nobody guesses an owner.
 * `& ` lines add an owner on top of the resolved one, so they decide nothing here.
 *
 * @example
 * // .codeowners: "? src/Administration/…/src/module/sw-media/** @shopware/product-cc-discovery"
 * findStatedOwner(codeowners, 'src/module/sw-media/component/sw-media-collapse') // → '? @shopware/product-cc-discovery'
 *
 * @private
 */
export function findStatedOwner(codeowners: string, directory: string): Owner | null {
    const start = codeowners.indexOf(BLOCK_START);
    const end = codeowners.indexOf(BLOCK_END);
    const handWritten = start === -1 ? codeowners : codeowners.slice(0, start) + codeowners.slice(end + BLOCK_END.length);
    const file = `${ADMIN_PATH}/${directory}/index.ts`;

    const winner = handWritten
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '' && !line.startsWith('#') && !line.startsWith('& '))
        .map((line) => {
            const optional = line.startsWith('? ');
            const [pattern, ...teams] = (optional ? line.slice(2) : line).trim().split(/\s+/);

            return { optional, pattern, teams };
        })
        .filter(({ pattern }) => patternToRegExp(pattern).test(file))
        .at(-1);

    if (!winner || winner.teams.length !== 1) {
        return null;
    }

    return `${winner.optional ? '? ' : ''}${winner.teams[0]}`;
}
