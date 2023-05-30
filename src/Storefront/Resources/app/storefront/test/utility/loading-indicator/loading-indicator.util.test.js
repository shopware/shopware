/* eslint-disable */
import LoadingIndicatorUtil, {INDICATOR_POSITION} from 'src/utility/loading-indicator/loading-indicator.util';
import template from './loading-inidicator-position.template.html'

function setupLoadingIndicator(elementSelector, position) {
    const element = document.querySelector(elementSelector);
    const loadingIndicator = new LoadingIndicatorUtil(element, position);

    return { element, loadingIndicator };
}

/**
 * @package content
 */
describe('LoadingIndicatorUtil position test', () => {
    beforeEach(() => {
        document.body.innerHTML = template;
    });

    test('position is before', () => {
        const elements = setupLoadingIndicator('#formBtn', INDICATOR_POSITION.BEFORE),
              expectedResult = `${LoadingIndicatorUtil.getTemplate()}Submit`;

        elements.loadingIndicator.create();

        expect(elements.loadingIndicator.parent.innerHTML).toBe(expectedResult);
        expect(elements.loadingIndicator.parent.innerHTML.endsWith('Submit')).toBeTruthy();
        expect(elements.loadingIndicator.parent.innerHTML.startsWith('Submit')).toBeFalsy();
    });

    test('position is after', () => {
        const elements = setupLoadingIndicator('#formBtn', INDICATOR_POSITION.AFTER),
              expectedResult = 'Submit' + LoadingIndicatorUtil.getTemplate();

        elements.loadingIndicator.create();

        expect(elements.loadingIndicator.parent.innerHTML).toBe(expectedResult);
        expect(elements.loadingIndicator.parent.innerHTML.startsWith('Submit')).toBeTruthy();
        expect(elements.loadingIndicator.parent.innerHTML.endsWith('Submit')).toBeFalsy();
    });

    test('position is inner', () => {
        const elements = setupLoadingIndicator('#formBtn', INDICATOR_POSITION.INNER);

        elements.loadingIndicator.create();

        expect(elements.loadingIndicator.parent.innerHTML).toBe(LoadingIndicatorUtil.getTemplate());
        expect(elements.loadingIndicator.parent.innerHTML.startsWith('Submit')).toBeFalsy();
        expect(elements.loadingIndicator.parent.innerHTML.endsWith('Submit')).toBeFalsy();
    });
});
