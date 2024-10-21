/**
 * @package admin
 */
import initializeHttpClient from 'src/app/init/http.init';

describe('src/app/init/http.init', () => {
    it('should initialize the http client', () => {
        expect(initializeHttpClient).toBeInstanceOf(Function);
        const result = initializeHttpClient();

        // Check if it is a Axios instance
        expect(result).toBeInstanceOf(Function);
        expect(result.request).toBeInstanceOf(Function);
        expect(result.getUri).toBeInstanceOf(Function);
        expect(result.delete).toBeInstanceOf(Function);
        expect(result.get).toBeInstanceOf(Function);
        expect(result.head).toBeInstanceOf(Function);
        expect(result.options).toBeInstanceOf(Function);
        expect(result.post).toBeInstanceOf(Function);
        expect(result.put).toBeInstanceOf(Function);
        expect(result.patch).toBeInstanceOf(Function);
    });
});
