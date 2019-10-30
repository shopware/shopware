import axios from 'axios';

export default class ApiService {
    constructor() {
        this.authInformation = {};
        this.basePath = '';

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


    request({ url, method, params, data }) {
        throw new Error('Implement method request()');
    }

    clearCache(action) {
        return this.delete(action).catch((err) => {
            global.logger.error(err);
            global.logger.lineBreak();
        });
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

    patch(url, data, params = {}) {
        return this.request({
            method: 'patch',
            data,
            url,
            params
        });
    }
}
