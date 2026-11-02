import { parseArguments } from './arguments';

describe('parseArguments', () => {
    it('asks git for the changed files by default', () => {
        // ACT
        const parsed = parseArguments([]);

        // ASSERT
        expect(parsed).toEqual({ files: [], base: null, dryRun: false, changedFiles: false });
    });

    it('takes files, a base and the flags', () => {
        // ACT
        const parsed = parseArguments([
            'src/app/main.ts',
            '--base',
            'HEAD~1',
            '--dry-run',
            'src/app/main.spec.js',
        ]);

        // ASSERT
        expect(parsed).toEqual({
            files: ['src/app/main.ts', 'src/app/main.spec.js'],
            base: 'HEAD~1',
            dryRun: true,
            changedFiles: false,
        });
    });

    it('rejects --base without a ref', () => {
        // ACT
        const parse = () => parseArguments(['--base', '--dry-run']);

        // ASSERT
        expect(parse).toThrow('--base needs a git ref');
    });

    it('rejects an unknown option', () => {
        // ACT
        const parse = () => parseArguments(['--dryrun']);

        // ASSERT
        expect(parse).toThrow('Unknown option --dryrun');
    });
});
