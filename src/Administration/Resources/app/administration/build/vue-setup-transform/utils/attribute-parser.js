/**
 * @sw-package framework
 */

const { Attributes } = require('./attributes');
const { isWhitespace } = require('./string-utils');
const { ShopwareSetupTransformError } = require('./transform-error');

/**
 * @typedef {import('./attributes').ParsedAttribute} ParsedAttribute
 *
 * @typedef {object} AttributeNameResult
 * @property {string} name
 * @property {number} nameStart
 *
 * @typedef {object} AttributeValueResult
 * @property {string} value
 * @property {number} end
 * @property {boolean} quoted
 */

class AttributeParser {
    /**
     * Parses the raw opening `<script>` attributes because Vue omits bound attributes from `attrs`.
     *
     * @param {string} attrsSource
     * @param {number} tagStart
     * @returns {Attributes}
     */
    static parse(attrsSource, tagStart) {
        const parser = new AttributeParser(attrsSource, tagStart);

        return parser.getAttributes();
    }

    /**
     * Stores parser state for one opening tag; construct through `AttributeParser.parse()`.
     *
     * @param {string} source
     * @param {number} tagStart
     */
    constructor(source, tagStart) {
        this.source = source;
        this.index = 0;
        this.tagStart = tagStart;
        /** @type {ParsedAttribute[]} */
        this.attributes = [];

        this.assertNoBackslash();
        this.parse();
    }

    /**
     * Returns the parsed wrapper object used by setup-mode normalization.
     *
     * @returns {Attributes}
     */
    getAttributes() {
        return new Attributes(this.attributes);
    }

    /**
     * Walks one opening tag from left to right and records static/boolean attributes.
     *
     * @returns {ParsedAttribute[]}
     */
    parse() {
        while (this.index < this.source.length) {
            this.skipWhitespace();

            if (this.index >= this.source.length || this.source[this.index] === '/') {
                break;
            }

            const nameResult = this.parseAttributeName();

            this.skipWhitespace();

            if (this.source[this.index] !== '=') {
                this.addBooleanAttribute(nameResult);
                continue;
            }

            this.index += 1;

            this.skipWhitespace();

            const valueResult = this.parseAttributeValue();
            this.addAttribute(nameResult, valueResult);
            this.index = valueResult.end;
        }

        return this.attributes;
    }

    /**
     * Rejects escapes up front so later string slicing never needs escape semantics.
     *
     * @returns {void}
     */
    assertNoBackslash() {
        const backslashIndex = this.source.indexOf('\\');

        if (backslashIndex !== -1) {
            throw new ShopwareSetupTransformError(
                'Backslashes are not supported in Shopware setup script attributes.',
                this.tagStart + backslashIndex,
            );
        }
    }

    /**
     * Records attributes such as `setup` where the value is represented by presence.
     *
     * @param {AttributeNameResult} nameResult
     */
    addBooleanAttribute(nameResult) {
        const { name, nameStart } = nameResult;
        this.attributes.push({
            name,
            value: true,
            quoted: false,
            hasValue: false,
            index: this.tagStart + nameStart,
        });
    }

    /**
     * Records quoted and unquoted attributes with their original source offset.
     *
     * @param {AttributeNameResult} nameResult
     * @param {AttributeValueResult} valueResult
     */
    addAttribute(nameResult, valueResult) {
        const { name, nameStart } = nameResult;
        const { value, quoted } = valueResult;

        this.attributes.push({
            name,
            value,
            quoted,
            hasValue: true,
            index: this.tagStart + nameStart,
        });
    }

    /**
     * Advances over SFC tag whitespace without consuming attribute characters.
     *
     * @returns {void}
     */
    skipWhitespace() {
        while (this.index < this.source.length && isWhitespace(this.source[this.index])) {
            this.index += 1;
        }
    }

    /**
     * Reads an attribute name until Vue tag syntax requires a value or separator.
     *
     * @returns {AttributeNameResult}
     */
    parseAttributeName() {
        const nameStart = this.index;

        while (
            this.index < this.source.length &&
            !isWhitespace(this.source[this.index]) &&
            this.source[this.index] !== '=' &&
            this.source[this.index] !== '/' &&
            this.source[this.index] !== '>'
        ) {
            this.index += 1;
        }

        const name = this.source.slice(nameStart, this.index);

        if (!name) {
            throw new ShopwareSetupTransformError('Malformed Vue SFC attribute.', this.tagStart + this.index);
        }

        return { name, nameStart };
    }

    /**
     * Reads the raw value form needed to reject unquoted Shopware mode attributes later.
     *
     * @returns {AttributeValueResult}
     */
    parseAttributeValue() {
        const quote = this.source[this.index];

        if (quote === '"' || quote === "'") {
            const valueStart = this.index + 1;
            const valueEnd = this.source.indexOf(quote, valueStart);

            if (valueEnd === -1) {
                throw new ShopwareSetupTransformError('Unclosed Vue SFC attribute value.', this.tagStart + this.index);
            }

            return {
                value: this.source.slice(valueStart, valueEnd),
                end: valueEnd + 1,
                quoted: true,
            };
        }

        let valueEnd = this.index;

        while (
            valueEnd < this.source.length &&
            !isWhitespace(this.source[valueEnd]) &&
            this.source[valueEnd] !== '>' &&
            this.source[valueEnd] !== '/'
        ) {
            valueEnd += 1;
        }

        return {
            value: this.source.slice(this.index, valueEnd),
            end: valueEnd,
            quoted: false,
        };
    }
}

module.exports = {
    AttributeParser,
};
