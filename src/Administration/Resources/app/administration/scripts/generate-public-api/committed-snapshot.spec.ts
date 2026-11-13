/**
 * @sw-package framework
 *
 * The snapshot in `public-api/` is committed, so a change to the public API shows up in the diff of its pull
 * request. That only holds while the committed files match the sources.
 */
import { ADMINISTRATION_ROOT, generateCurrentSnapshot, readCommittedSnapshot } from './committed-snapshot';
import { GENERATE_COMMAND } from './print-shared';
import { snapshotOf } from './snapshot';
import { readSourceTree, sourceTreeOf } from './source-tree';

function blockNames(files: Record<string, string>): Set<string> {
    const { components } = JSON.parse(files['index.json']) as { components: Record<string, { blocks: string[] }> };

    return new Set(Object.values(components).flatMap(({ blocks }) => blocks));
}

describe('scripts/generate-public-api/committed-snapshot', () => {
    const { files } = generateCurrentSnapshot();

    it('still has every block of the committed snapshot', () => {
        const blocks = blockNames(files);
        const removed = [...blockNames(readCommittedSnapshot().files)].filter((block) => !blocks.has(block)).sort();

        expect(
            removed,
            'Breaking change detected! These blocks are gone from the templates, so extensions that override them ' +
                `stop rendering. Keep them, or deprecate them first. Regenerating the snapshot with \`${GENERATE_COMMAND}\` ` +
                'only silences this test.',
        ).toEqual([]);
    });

    it('matches the Administration sources', () => {
        const committed = readCommittedSnapshot().files;
        const stale = [...new Set([...Object.keys(files), ...Object.keys(committed)])]
            .filter((file) => files[file] !== committed[file])
            .sort();

        expect(stale, `The snapshot in public-api/ is stale. Run \`${GENERATE_COMMAND}\` and commit the result.`).toEqual(
            [],
        );
    });

    it('is byte-identical in a second run', () => {
        expect(generateCurrentSnapshot().files).toEqual(files);
    });

    it('does not depend on the order in which the source files are found', () => {
        const tree = readSourceTree(ADMINISTRATION_ROOT);
        const reversed = sourceTreeOf([...tree.files].reverse(), (file) => tree.read(file));

        expect(snapshotOf(reversed, { majorFlags: ['v6.8.0.0'] })).toEqual(snapshotOf(tree, { majorFlags: ['v6.8.0.0'] }));
    });

    it('contains nothing that differs between two checkouts', () => {
        const machineSpecific = Object.entries(files)
            .filter(
                ([file, content]) => content.includes(ADMINISTRATION_ROOT) || content.includes('\r') || file.includes('\\'),
            )
            .map(([file]) => file);

        expect(machineSpecific).toEqual([]);
    });
});
