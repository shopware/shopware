/**
 * @sw-package framework
 */

import {
    Attributes,
    type ParsedAttribute,
} from './attributes';
import { isWhitespace } from './string-utils';
import { ShopwareSetupTransformError } from './transform-error';

type AttributeNameResult = {
    name: string,
    nameStart: number,
    nameEnd: number,
};

type AttributeValueResult = {
    value: string,
    end: number,
    quoted: boolean,
};

class AttributeParser {
    /**
     * Parses the raw opening `<script>` attributes because Vue omits bound attributes from `attrs`.
     */
    static parse(attrsSource: string, tagStart: number): Attributes {
        const parser = new AttributeParser(attrsSource, tagStart);

        return parser.getAttributes();
    }

    private readonly source: string;

    private index: number;

    private readonly tagStart: number;

    private readonly attributes: ParsedAttribute[];

    /**
     * Stores parser state for one opening tag; construct through `AttributeParser.parse()`.
     */
    constructor(source: string, tagStart: number) {
        this.source = source;
        this.index = 0;
        this.tagStart = tagStart;
        this.attributes = [];

        this.assertNoBackslash();
        this.parse();
    }

    /**
     * Returns the parsed wrapper object used by setup-mode normalization.
     */
    getAttributes(): Attributes {
        return new Attributes(this.attributes);
    }

    /**
     * Walks one opening tag from left to right and records static/boolean attributes.
     */
    parse(): ParsedAttribute[] {
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
     */
    assertNoBackslash(): void {
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
     */
    addBooleanAttribute(nameResult: AttributeNameResult): void {
        const { name, nameStart, nameEnd } = nameResult;
        const start = nameStart;
        const end = nameEnd;

        this.attributes.push({
            name,
            value: true,
            quoted: false,
            hasValue: false,
            index: this.tagStart + nameStart,
            start,
            end,
            source: this.source.slice(start, end),
        });
    }

    /**
     * Records quoted and unquoted attributes with their original source offset.
     */
    addAttribute(nameResult: AttributeNameResult, valueResult: AttributeValueResult): void {
        const { name, nameStart } = nameResult;
        const { value, quoted } = valueResult;
        const start = nameStart;
        const end = valueResult.end;

        this.attributes.push({
            name,
            value,
            quoted,
            hasValue: true,
            index: this.tagStart + nameStart,
            start,
            end,
            source: this.source.slice(start, end),
        });
    }

    /**
     * Advances over SFC tag whitespace without consuming attribute characters.
     */
    skipWhitespace(): void {
        while (this.index < this.source.length && isWhitespace(this.source[this.index])) {
            this.index += 1;
        }
    }

    /**
     * Reads an attribute name until Vue tag syntax requires a value or separator.
     */
    parseAttributeName(): AttributeNameResult {
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

        return {
            name,
            nameStart,
            nameEnd: this.index,
        };
    }

    /**
     * Reads the raw value form needed to reject unquoted Shopware mode attributes later.
     */
    parseAttributeValue(): AttributeValueResult {
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

export {
    AttributeParser,
};
