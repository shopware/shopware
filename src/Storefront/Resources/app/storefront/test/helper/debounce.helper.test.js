import Debouncer from 'src/helper/debouncer.helper';

/**
 * @package storefront
 */
describe('debouncer helper', () => {
    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test('cancels a pending invocation and allows subsequent calls', () => {
        const callback = jest.fn();
        const debounced = Debouncer.debounce(callback, 800);

        debounced('stale');
        debounced.cancel();
        jest.advanceTimersByTime(800);
        expect(callback).not.toHaveBeenCalled();

        debounced('current');
        jest.advanceTimersByTime(800);
        expect(callback).toHaveBeenCalledTimes(1);
        expect(callback).toHaveBeenCalledWith('current');
    });

    test('cancels the leading invocation of immediate mode as well', () => {
        const callback = jest.fn();
        const debounced = Debouncer.debounce(callback, 800, true);

        debounced('stale');
        debounced.cancel();
        jest.advanceTimersByTime(800);

        expect(callback).not.toHaveBeenCalled();
    });

    test('flushes a pending invocation with the given arguments right away', () => {
        const callback = jest.fn();
        const debounced = Debouncer.debounce(callback, 800);

        debounced('stale');
        debounced.flush('current');
        expect(callback).toHaveBeenCalledTimes(1);
        expect(callback).toHaveBeenCalledWith('current');

        jest.advanceTimersByTime(800);
        expect(callback).toHaveBeenCalledTimes(1);
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
    });
});
