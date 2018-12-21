import axios from 'axios';

export default class ApiService {
    constructor(basePath) {
        this.authInformation = {};
        this.basePath = basePath;

        this.client = axios.create({
            baseURL: `${this.getBasicPath(this.basePath)}`
        });
    }

    getBasicPath(name) {
        throw new Error('Implement method getBasicPath()');
    }

    getHeaders() {
        throw new Error('Implement method getHeaders()');
    }

    loginByUserName() {
        throw new Error('Implement method loginByUserName()');
    }

    get(url, params = {}) {
        return this.request({
            method: 'get',
            url,
            params
        });
    }

    post(url, data, params = {}) {
        return this.request({
            method: 'post',
            url,
            data,
            params
        });
    }

    delete(url, params = {}) {
        return this.request({
            method: 'delete',
            url,
            params
        });
    }

    head(url, params = {}) {
        return this.request({
            method: 'head',
            url,
            params
        });
    }

    options(url, params = {}) {
        return this.request({
            method: 'options',
            url,
            params
        });
    }

    put(url, data, params = {}) {
        return this.request({
            method: 'put',
            data,
            url,
            params
        });
    }

    path(url, data, params = {}) {
        return this.request({
            method: 'put',
            data,
            url,
            params
        });
    }

    request({url, method, params, data}) {
        return this.loginByUserName().then(() => {
            const requestConfig = {
                headers: this.getHeaders(),
                url,
                method,
                params,
                data
            };
            return this.client.request(requestConfig).then((response) => {
                if (Array.isArray(response.data.data) && response.data.data.length === 1) {
                    return response.data.data[0];
                }
                return response.data.data;
            });
        });
    }
}
