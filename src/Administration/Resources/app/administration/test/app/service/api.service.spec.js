import ApiService from 'src/core/service/api.service';

describe('src/app/service/api.service.js', () => {
    describe('makeQueryParams', () => {
        it('should handle empty dictionary', () => {
            expect(ApiService.makeQueryParams()).toEqual('');
            expect(ApiService.makeQueryParams({})).toEqual('');
        });

        it('should handle one param', () => {
            expect(ApiService.makeQueryParams({
                key: 'value'
            })).toEqual('?key=value');
        });

        it('should handle multiple params', () => {
            expect(ApiService.makeQueryParams({
                key: 'value',
                key2: 'value2'
            })).toEqual('?key=value&key2=value2');
        });
    });
});
