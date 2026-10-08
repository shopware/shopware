/**
 * @sw-package framework
 */
import ApiServices from 'src/core/service/api';
import ConfigApiService from 'src/core/service/api/config.api.service';
import SnippetApiService from 'src/core/service/api/snippet.api.service';
import StoreApiService from 'src/core/service/api/store.api.service';

describe('src/core/service/api/index.ts', () => {
    it('should collect the default export of every api service', () => {
        const services = ApiServices();

        expect(services).toEqual(
            expect.arrayContaining([
                ConfigApiService,
                SnippetApiService,
                StoreApiService,
            ]),
        );
        expect(services.length).toBeGreaterThan(50);
    });
});
