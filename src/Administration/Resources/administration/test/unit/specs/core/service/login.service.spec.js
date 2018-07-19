import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';

import LoginService from 'src/core/service/login.service';

const httpMock = new MockAdapter(axios);
let mockedLoginService;

const realLoginService = Shopware.Application.getContainer('service').loginService;

describe('core/service/login.service.js', () => {
    // Create a new instance of the service for each test
    beforeEach(() => {
        mockedLoginService = new LoginService(axios);
    });

    // Resets the mocking adapter
    afterEach(() => {
        httpMock.reset();
        mockedLoginService.clearBearerAuthentication();
    });

    it('should request the token and expiry date from the server', () => {
        return realLoginService.loginByUsername('admin', 'shopware').then((response) => {
            expect(response.status).to.equal(200);
            expect(response.data.expiry).to.equal(3600);
        });
    });

    it('should store the bearer authentication object in the localStorage', () => {
        const authObject = mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const localStorageObject = JSON.parse(localStorage.getItem(mockedLoginService.getLocalStorageKey()));

        expect(localStorageObject).to.deep.equal(authObject);
    });

    it('should get the bearer authentication object from the localStorage', () => {
        const authObject = mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const localStorageObject = mockedLoginService.getBearerAuthentication();

        expect(localStorageObject).to.deep.equal(authObject);
    });

    it('should clear the localStorage entry', () => {
        const authObject = mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );

        const localStorageObject = JSON.parse(localStorage.getItem(mockedLoginService.getLocalStorageKey()));

        expect(localStorageObject).to.deep.equal(authObject);

        mockedLoginService.clearBearerAuthentication();
        const clearedObject = localStorage.getItem(mockedLoginService.getLocalStorageKey());

        expect(clearedObject).to.equal(null);
    });

    it('should provide the bearer token', () => {
        const authObject = mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const token = mockedLoginService.getToken();

        expect(token).to.equal(authObject.token);
    });

    it('should provide the expiry date', () => {
        const authObject = mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const expiry = mockedLoginService.getExpiry();

        expect(expiry).to.equal(authObject.expiry);
    });

    it('should validate the expiry date', () => {
        mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const expiry = mockedLoginService.getExpiry();

        expect(mockedLoginService.validateExpiry(expiry)).to.equal(true);
    });

    it('should provide the localStorage key', () => {
        const key = mockedLoginService.getLocalStorageKey();

        expect(key).to.equal('bearerAuth');
    });

    it('should set a new the localStorage key', () => {
        mockedLoginService.setLocalStorageKey('newStorageKey', false);

        expect(mockedLoginService.getLocalStorageKey()).to.equal('newStorageKey');
    });

    it('should set a new the localStorage key and clear the previous entry', () => {
        mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const oldKey = mockedLoginService.getLocalStorageKey();
        const newKey = mockedLoginService.setLocalStorageKey('newStorageKey');

        const authObject = mockedLoginService.setBearerAuthentication(
            'foobar',
            9999999999
        );

        expect(localStorage.getItem(oldKey)).to.equal(null);
        expect(JSON.parse(localStorage.getItem(newKey))).to.deep.equal(authObject);
    });
});
