/**
 * @sw-package framework
 *
 * The snapshot in `public-api/` is committed, so a change to the public API shows up in the diff of its pull
 * request. That only holds while the committed files match the sources.
 */
import { ADMINISTRATION_ROOT, generateCurrentSnapshot, majorFlagsOf, readCommittedSnapshot } from './committed-snapshot';
import { GENERATE_COMMAND } from './print-shared';
import { snapshotOf } from './snapshot';
import { readSourceTree, sourceTreeOf } from './source-tree';

describe('scripts/generate-public-api/committed-snapshot', () => {
    const { files } = generateCurrentSnapshot();

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

    describe('majorFlagsOf', () => {
        const flags = [
            { name: 'v6.7.0.0', default: true },
            { name: 'v6.9.0.0', default: false },
            { name: 'v6.8.0.0', default: false },
            { name: 'EXAMPLE_REWORK', default: false, major: 'v6.8.0.0' },
            { name: 'LATER_REWORK', default: false, major: 'v6.9.0.0' },
            { name: 'EXPERIMENT', default: false },
        ];

        it('takes the next major and the flags that belong to it', () => {
            expect(majorFlagsOf({ shopware: { feature: { flags } } })).toEqual([
                'v6.8.0.0',
                'EXAMPLE_REWORK',
            ]);
        });

        it('fails when every version flag is on already', () => {
            expect(() => majorFlagsOf({ shopware: { feature: { flags: [{ name: 'v6.7.0.0', default: true }] } } })).toThrow(
                'feature.yaml has no version flag that is still off',
            );
        });
    });
});
