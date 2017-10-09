import CsrfFactory from 'src/core/factory/csrf.factory';

export default function initializeCSRFToken(app, configuration, done) {
    CsrfFactory.requestToken().then((token) => {
        configuration.csrfToken = token;

        done(configuration);
    });
}
