/**
 * @package admin
 */
import InvalidActionButtonParameterError from './InvalidActionButtonParameterError';

describe('InvalidActionButtonParameterError.ts', () => {
    it('should be an error instance', () => {
        const error = new InvalidActionButtonParameterError('Foo');

        expect(error instanceof Error).toBe(true);
    });

    it('should have expected message', () => {
        const error = new InvalidActionButtonParameterError('Foo');

        expect(error.message).toBe('Foo');
    });

    it('should have expected name', () => {
        const error = new InvalidActionButtonParameterError('Foo');

        expect(error.name).toBe('InvalidActionButtonParameterError');
    });
});
