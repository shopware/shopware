import { dropUntestedFiles } from './drop-untested-baseline';

describe('dropUntestedFiles', () => {
    it('removes moved files and the files of moved directories from missingTests, and keeps the others', () => {
        // ARRANGE
        const source =
            "const missingTests = [\n    'a/index.js',\n    'b/index.js',\n    'c/index.js',\n];\nconst other = ['a/index.js'];\n";

        // ACT
        const result = dropUntestedFiles(source, ['a/index.js', 'c']);

        // ASSERT
        expect(result).toBe("const missingTests = [\n    'b/index.js',\n];\nconst other = ['a/index.js'];\n");
    });
});
