import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import LoginService from 'src/core/service/login.service';

const httpMock = new MockAdapter(axios);
let loginService;

describe('core/service/login.service.js', () => {
    // Create a new instance of the service for each test
    beforeEach(() => {
        loginService = new LoginService(axios);
    });

    // Resets the mocking adapter
    afterEach(() => {
        httpMock.reset();
        loginService.clearBearerAuthentication();
    });

    it('should request the token and expiry date from the server', () => {
        httpMock.onPost('auth').reply(() => {
            return [200, {
                token: 'foobar',
                expiry: 9999999999
            }];
        });

        return loginService.loginByUsername('demo', 'demo').then((response) => {
            expect(response.status).to.equal(200);
            expect(response.data.token).to.equal('foobar');
            expect(response.data.expiry).to.equal(9999999999);
        });
    });

    it('should store the bearer authentication object in the localStorage', () => {
        const authObject = loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const localStorageObject = JSON.parse(localStorage.getItem(loginService.getLocalStorageKey()));

        expect(localStorageObject).to.deep.equal(authObject);
    });

    it('should get the bearer authentication object from the localStorage', () => {
        const authObject = loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const localStorageObject = loginService.getBearerAuthentication();

        expect(localStorageObject).to.deep.equal(authObject);
    });

    it('should clear the localStorage entry', () => {
        const authObject = loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );

        const localStorageObject = JSON.parse(localStorage.getItem(loginService.getLocalStorageKey()));

        expect(localStorageObject).to.deep.equal(authObject);

        loginService.clearBearerAuthentication();
        const clearedObject = localStorage.getItem(loginService.getLocalStorageKey());

        expect(clearedObject).to.equal(null);
    });

    it('should provide the bearer token', () => {
        const authObject = loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const token = loginService.getToken();

        expect(token).to.equal(authObject.token);
    });

    it('should provide the expiry date', () => {
        const authObject = loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const expiry = loginService.getExpiry();

        expect(expiry).to.equal(authObject.expiry);
    });

    it('should validate the expiry date', () => {
        loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const expiry = loginService.getExpiry();

        expect(loginService.validateExpiry(expiry)).to.equal(true);
    });

    it('should provide the localStorage key', () => {
        const key = loginService.getLocalStorageKey();

        expect(key).to.equal('bearerAuth');
    });

    it('should set a new the localStorage key', () => {
        loginService.setLocalStorageKey('newStorageKey', false);

        expect(loginService.getLocalStorageKey()).to.equal('newStorageKey');
    });

    it('should set a new the localStorage key and clear the previous entry', () => {
        loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const oldKey = loginService.getLocalStorageKey();
        const newKey = loginService.setLocalStorageKey('newStorageKey');

        const authObject = loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );

        expect(localStorage.getItem(oldKey)).to.equal(null);
        expect(JSON.parse(localStorage.getItem(newKey))).to.deep.equal(authObject);
    });
});
