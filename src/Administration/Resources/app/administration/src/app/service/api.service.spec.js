/**
 * @package admin
 */

import ApiService from 'src/core/service/api.service';

describe('src/app/service/api.service.js', () => {
    describe('makeQueryParams', () => {
        it('should handle empty dictionary', async () => {
            expect(ApiService.makeQueryParams()).toBe('');
            expect(ApiService.makeQueryParams({})).toBe('');
        });

        it('should handle one param', async () => {
            expect(
                ApiService.makeQueryParams({
                    key: 'value',
                }),
            ).toBe('?key=value');
        });

        it('should handle multiple params', async () => {
            expect(
                ApiService.makeQueryParams({
                    key: 'value',
                    key2: 'value2',
                }),
            ).toBe('?key=value&key2=value2');
        });
    });
});
