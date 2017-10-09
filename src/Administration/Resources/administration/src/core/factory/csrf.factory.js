import Axios from 'axios';

export default {
    requestToken
};

let csrfToken = '';

function storeToken(response) {
    csrfToken = response.headers['x-csrf-token'];
    return csrfToken;
}

function generateAndStoreToken() {
    return Axios({
        method: 'get',
        url: 'http://shopware-next.local/backend/CSRFToken/generate',
        headers: {
            ignoreCSRFToken: true
        }
    }).then(storeToken);
}

function requestToken() {
    if (csrfToken.length) {
        return Promise.resolve(csrfToken);
    }

    return generateAndStoreToken();
}
