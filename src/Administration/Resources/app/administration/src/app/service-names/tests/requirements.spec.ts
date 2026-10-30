import { repositoryFactory } from '../index';

/**
 * @sw-package framework
 */

describe('service-names', () => {
    /**
     * @requirement A service keeps the name it is registered under.
     * Extensions inject services by these names, so a new name would break them.
     */
    it('names the repository factory "repositoryFactory"', () => {
        expect(repositoryFactory).toBe('repositoryFactory');
    });
});
