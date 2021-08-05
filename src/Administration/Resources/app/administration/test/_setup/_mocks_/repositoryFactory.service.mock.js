// eslint-disable-next-line import/no-extraneous-dependencies
import chalk from 'chalk';
import RepositoryFactory from 'src/core/data/repository-factory.data';
import EntityHydrator from 'src/core/data/entity-hydrator.data';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import EntityFactory from 'src/core/data/entity-factory.data';
import ErrorResolverError from 'src/core/data/error-resolver.data';
import createHTTPClient from 'src/core/factory/http.factory';
// eslint-disable-next-line import/no-extraneous-dependencies
import MockAdapter from 'axios-mock-adapter';
import EntitySchema from '../../_mocks_/entity-schema.json';

// Add all entities from entity-schema
Object.entries(EntitySchema).forEach(([entityName, entityInformation]) => {
    Shopware.EntityDefinition.add(entityName, entityInformation);
});

// This function throws an error if some request has no mocked return value
function throwMissingImplementationError(config) {
    if (!global.repositoryFactoryMock.showError) {
        return;
    }

    console.error(chalk.yellow(`
You should to implement mock data for this route: "${config.url}".

############### Example ###############

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: '${config.method.charAt(0).toUpperCase() + config.method.slice(1)}',
    url: '${config.url}',
    status: 200,
    response: {
        data: [
            {
                id: YourId,
                attributes: {
                    id: YourId
                }
            }
        ]
    }
});

############### Example End ###############

You can disable this error with this code:

global.repositoryFactoryMock.showError = false;
`));
}

// This registry contains all customs test responses (with axios-mock-adapter)
class ResponseRegistry {
    constructor() {
        this.registry = [];
    }

    addResponse({ url, method, response, status = 200, warning = false }) {
        this.registry.unshift({
            url,
            method,
            status,
            response,
            warning,
        });
    }

    getResponse({ url, method }) {
        return this.registry.find(response => {
            return url.match(response.url) && response.method.toUpperCase() === method.toUpperCase();
        });
    }
}

// create a mock for the httpClient for creating custom responses
function clientMockFactory() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    const responses = new ResponseRegistry();

    clientMock.onAny().reply(config => {
        const customResponse = responses.getResponse({
            url: config.url,
            method: config.method,
        });

        if (customResponse) {
            if (customResponse.warning) {
                throwMissingImplementationError(config);
            }

            return [customResponse.status, customResponse.response];
        }

        throwMissingImplementationError(config);
        return [500, {}];
    });

    // Add default responses
    responses.addResponse({
        method: 'POST',
        url: /\/api\/v\d\/search\/.*/g,
        status: 200,
        warning: true,
        response: {
            data: [],
            meta: {
                total: 0,
            },
        },
    });

    return { client, clientMock, responses };
}

// create the httpClient, the clientMock and the responses
const { client: httpClient, clientMock, responses } = clientMockFactory();

global.repositoryFactoryMock = {
    httpClient,
    clientMock,
    responses,
    showError: true,
};

const hydrator = new EntityHydrator();
const changesetGenerator = new ChangesetGenerator();
const entityFactory = new EntityFactory();
const errorResolver = new ErrorResolverError();

const repositoryFactory = new RepositoryFactory(
    hydrator,
    changesetGenerator,
    entityFactory,
    httpClient,
    errorResolver,
);

export default repositoryFactory;
