import * as serviceNames from '../index';

/**
 * @sw-package framework
 */

describe('service-names', () => {
    /**
     * @requirement A service keeps the name it is registered under.
     * Extensions inject services by these names, so a new name would break them.
     */
    it('names every service by its registered name', () => {
        const names = Object.entries(serviceNames);

        const renamed = names.filter(([constant, name]) => constant !== name);

        expect(renamed).toEqual([]);
    });
});
