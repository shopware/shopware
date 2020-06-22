import RepositoryData from 'src/core/data-new/repository.data';

function mockContext() {
    return {
        apiPath: 'http://shopware.local/api',
        apiResourcePath: 'http://shopware.local/api/v2',
        apiVersion: 2,
        assetsPath: 'http://shopware.local/bundles/',
        basePath: '',
        host: 'shopware.local',
        inheritance: false,
        installationPath: 'http://shopware.local',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        currencyId: '7924299acc9641bfb8237a06e5aa0fa4',
        liveVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        pathInfo: '/admin',
        port: 80,
        scheme: 'http',
        schemeAndHttpHost: 'http://shopware.local',
        uri: 'http://shopware.local/admin',
        authToken: {
            access: 'BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco'
        }
    };
}

function createRepositoryData() {
    return new RepositoryData(
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        undefined,
        {}
    );
}

describe('repository.data.js', () => {
    it('should build the correct headers', () => {
        const repositoryData = createRepositoryData('language');
        const actualHeaders = repositoryData.buildHeaders(mockContext());
        const exptectedHeaders = {
            'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            Accept: 'application/vnd.api+json',
            Authorization: 'Bearer BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
            'Content-Type': 'application/json',
            'sw-api-compatibility': true,
            'sw-currency-id': '7924299acc9641bfb8237a06e5aa0fa4'
        };

        expect(actualHeaders).toEqual(exptectedHeaders);
    });
});
