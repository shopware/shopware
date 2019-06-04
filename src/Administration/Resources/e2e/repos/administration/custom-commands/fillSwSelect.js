/**
 * Finds a sw-select component in the Administration. The method uses a css selector to find the element on the page,
 * removes a preselected items (if configured). If a search term is provided it will be entered to the input and after
 * the search the first result gets selected.
 *
 * @param {String} selector
 * @param {Object} obj
 * @param {String} obj.value
 * @param {Boolean} obj.clearField
 * @param {Boolean} obj.isMulti
 * @param {Number} obj.resultPosition
 * @returns {exports}
 */
exports.command = function fillSwSelect(
    selector,
    { value, clearField = false, isMulti = false, searchTerm = null, resultPosition = 0 }
) {
    searchTerm = searchTerm || value;
    this.waitForElementVisible(selector)
        .getAttribute(selector, 'class', (classList) => {
            const selectors = getCorrectSelectors(classList.value.split(' '));

            if (isMulti) {
                return fillMultiSelect.call(this, selectors);
            }

            return fillSingleSelect.call(this, selectors);
        });
    return this;

    function getCorrectSelectors(classList) {
        if (isMulti) {
            return getMultiSelectors(classList);
        }
        return getSingleSelectors(classList);
    }

    function getMultiSelectors(classList) {
        const selectors = {
            input: '.sw-select__input',
            item: '.sw-label',
            removeItem: '.sw-label__dismiss',
            option: '.sw-select-option',
            loader: '.sw-select__indicators .sw-loader',
            results: '.sw-select__results'
        };
        if (classList.includes('sw-multi-select')) {
            Object.keys(selectors).forEach((key) => {
                selectors[key] = selectors[key].replace('sw-select', 'sw-multi-select');
            });
        }

        return selectors;
    }

    function getSingleSelectors(classList) {
        const selectors = {
            input: '.sw-select__input-single',
            placeholder: '.sw-select__placeholder',
            option: '.sw-select-option',
            loader: '.sw-select__indicators .sw-loader',
            results: '.sw-select__results',
            selection: '.sw-select__single-selection'
        };
        if (classList.includes('sw-single-select')) {
            Object.keys(selectors).forEach((key) => {
                selectors[key] = selectors[key].replace('sw-select', 'sw-single-select');
            });
        }

        return selectors;
    }

    function fillSingleSelect(selectors) {
        this.click(selector, (clickResult) => {
            this.click(selector);
            global.logger.error(`Element click: "${clickResult.status}" / Retry.`);
        });

        this.waitForElementVisible(`${selector} ${selectors.results}`);

        chooseCorrectOption.call(this, selectors);

        // expect the placeholder for an empty select field not be shown and search for the value
        this.waitForElementNotPresent(`${selector} ${selectors.placeholder}`)
            .expect.element(`${selector} ${selectors.selection}`).to.have.text.that.contains(value);

        return this;
    }

    function fillMultiSelect(selectors) {
        if (clearField) {
            this.click(`${selector} ${selectors.removeItem}`)
                .waitForElementNotPresent(`${selector} ${selectors.item}`);
        }

        chooseCorrectOption.call(this, selectors);
        // in multi selects we can check if the value is a selected item
        this.expect.element(`${selector} ${selectors.item}`).to.have.text.that.contains(value);

        console.log(`${selector} ${selectors.input}`);

        // close search results
        this.clearField(`${selector} ${selectors.input}`)
            .setValue(`${selector} ${selectors.input}`, this.Keys.ESCAPE)
            .waitForElementNotPresent(`${selector} ${selectors.results}`);

        return this;
    }

    function chooseCorrectOption(selectors) {
        this.fillField(`${selector} ${selectors.input}`, searchTerm);
        this.waitForElementNotPresent(`${selector} ${selectors.loader}`)
            .waitForElementVisible(`${selector} ${selectors.results}`);

        this.assert.containsText(`${selectors.option}--${resultPosition}`, value);
        this.click(`${selectors.option}--${resultPosition}`);
    }
};
