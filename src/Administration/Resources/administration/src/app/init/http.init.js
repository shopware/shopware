import HttpFactory from 'src/core/factory/http.factory';

export default function initializeHttpClient(app, configuration, done) {
    configuration.httpClient = HttpFactory.createClientWithToken(
        configuration.csrfToken,
        configuration.context
    );

    done(configuration);
}
