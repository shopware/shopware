import { createCodes } from 'src/module/sw-promotion/service/individual-code-generator.service';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/service/individual-code-generator.service.js', () => {
    it('should have 10 codes with individual code', async () => {
        const codes = createCodes('code-%s', 10, [], 1);

        expect(codes.length).toBe(10);
    });

    it('should have 1 codes with invalid individual code', async () => {
        const codes = createCodes('code', 10, [], 1);

        expect(codes.length).toBe(1);
        expect(codes[0]).toEqual({
            promotionId: 1,
            code: 'code'
        });
    });

    it('should have 51 codes with existing codes when generate invalid individual code', async () => {
        let codes = createCodes('code-%s', 52, [], 1);

        expect(codes.length).toBe(52);
        expect(codes.find(code => code.code === 'code-Y')).toEqual({
            promotionId: 1,
            code: 'code-Y'
        });

        codes = createCodes('code-%s', 52, ['code-Y'], 1);

        expect(codes.length).toBe(51);
        expect(codes.find(code => code.code === 'code-Y')).toBe(undefined);
    });
});
