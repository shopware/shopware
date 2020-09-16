import generator from 'src/module/sw-promotion/service/code-generator.service';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/service/code-generator.service.js', () => {
    it('should also work if no placeholder has been provided', async () => {
        const code = generator.generateCode('my-code');
        expect(code).toBe('my-code');
    });

    it('should keep the parts of the pattern that are no placeholders', async () => {
        const code = generator.generateCode('%smy_in_between-text%d');
        expect(code).toMatch('my_in_between-text');
    });

    it('should only generate 1 random character for the placeholder %s', async () => {
        const code = generator.generateCode('code-%s');
        const character = code.replace('code-', '');
        expect(character).toMatch(/[a-zA-Z]{1}/);
    });

    it('should only generate 1 random number for the placeholder %d', async () => {
        const code = generator.generateCode('code-%d');
        const character = code.replace('code-', '');
        expect(character).toMatch(/[0-9]{1}/);
    });

    it('should only have 1 permutation without wildcards', async () => {
        const count = generator.getPermutationCount('code');
        expect(count).toBe(1);
    });

    it('should have 10 permutations with a single digit wildcard', async () => {
        const count = generator.getPermutationCount('code-%d');
        expect(count).toBe(10);
    });

    it('should have 52 permutations with a single string wildcard', async () => {
        const count = generator.getPermutationCount('code-%s');
        // 26 characters in upper and lower case
        expect(count).toBe(52);
    });

    it('should have 100 (10^2) permutations with 2 digit wildcards', async () => {
        const count = generator.getPermutationCount('code-%d-%d');
        expect(count).toBe(100);
    });

    it('should have 520 (10^1 * 52^1) permutations with 1 digit and 1 string wildcard', async () => {
        const count = generator.getPermutationCount('code-%d-%s');
        expect(count).toBe(520);
    });

    it('should have 100k (10^5) permutations with 5 digit wildcards', async () => {
        const count = generator.getPermutationCount('code-%d%d%d%d%d');
        expect(count).toBe(100000);
    });

    it('should have 140.608 (52^3) permutations with 3 string wildcards', async () => {
        const count = generator.getPermutationCount('code-%s%s%s');
        expect(count).toBe(140608);
    });
});
