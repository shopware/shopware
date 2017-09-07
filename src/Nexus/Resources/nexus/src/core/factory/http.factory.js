import Axios from 'axios';

export default {
    createClientWithToken
};

/* function configureRequestCSRFInterceptor(httpClient, token) {
    httpClient.interceptors.request.use((config) => {
        config.headers['X-CSRF-TOKEN'] = token;

        return config;
    }, Promise.reject);
} */

function createClientWithToken(token, baseURL) {
    const httpClient = Axios.create({
        baseURL: `${baseURL}/api`
    });

    // configureRequestCSRFInterceptor(httpClient, token);

    return httpClient;
}
