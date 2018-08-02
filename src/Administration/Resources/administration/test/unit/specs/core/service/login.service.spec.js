import { itAsync } from '../../../async-helper';

let loginService;

describe('core/service/login.service.js', () => {
    beforeEach(() => {
        loginService = Shopware.Application.getContainer('service').loginService;
    });
    afterEach(() => {
        loginService.clearBearerAuthentication();
    });

    itAsync('should request the token and expiry date from the server', (done) => {
        loginService.loginByUsername('admin', 'shopware').then((response) => {
            const data = response.data;
            expect(response.status).to.be.equal(200);
            expect(data).to.be.an('object');
            expect(data.expires_in).to.be.equal(3600);
            expect(data.access_token).to.be.a('string');
            expect(data.refresh_token).to.be.a('string');
            expect(data.token_type).to.equal('Bearer');
            done();
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
        const oldKey = loginService.getLocalStorageKey();
        loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );
        const newKey = loginService.setLocalStorageKey('storageKey');

        const authObject = loginService.setBearerAuthentication(
            'foobar',
            9999999999
        );

        expect(localStorage.getItem(oldKey)).to.equal(null);
        expect(JSON.parse(localStorage.getItem(newKey))).to.deep.equal(authObject);
    });
});
