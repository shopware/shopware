import Debouncer from 'src/helper/debouncer.helper';

/**
 * @package storefront
 */
describe('debouncer helper', () => {
    beforeEach(() => {
        jest.useFakeTimers();
    });

    test('it calls a function only once when called before timeout fired', () => {
        const spy = jest.fn();

        const debounced = Debouncer.debounce(spy, 5000);

        debounced(1);
        debounced(2);

        jest.runAllTimers();

        expect(spy).toHaveBeenCalledTimes(1);
        expect(spy).toHaveBeenCalledWith(2);
    });

    test('it calls different callbacks once', () => {
        const firstSpy = jest.fn();
        const secondSpy = jest.fn();

        let debounced = Debouncer.debounce(firstSpy, 5000);
        debounced(1);

        debounced = Debouncer.debounce(secondSpy, 5000);
        debounced(2);

        jest.runAllTimers();

        expect(firstSpy).toHaveBeenCalledTimes(1);
        expect(firstSpy).toHaveBeenCalledWith(1);

        expect(secondSpy).toHaveBeenCalledTimes(1);
        expect(secondSpy).toHaveBeenCalledWith(2);
    });

    test('is called immediately and delayed with same args', () => {
        const spy = jest.fn();

        const debounced = Debouncer.debounce(spy, 1000, true);

        debounced(1);

        jest.runAllTimers();

        expect(spy).toHaveBeenCalledTimes(2);
        expect(spy).toHaveBeenNthCalledWith(1, 1);
        expect(spy).toHaveBeenNthCalledWith(2, 1);
    });

    test('is called immediately with given args even when cancelled', () => {
        const spy = jest.fn();

        const debounced = Debouncer.debounce(spy, 1000, true);

        debounced(1);
        debounced(2);

        jest.runAllTimers();

        expect(spy).toHaveBeenCalledTimes(2);
        expect(spy).toHaveBeenNthCalledWith(1, 1);
        expect(spy).toHaveBeenNthCalledWith(2, 2);
    })
});
