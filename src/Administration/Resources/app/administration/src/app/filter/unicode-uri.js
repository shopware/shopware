import Punycode from 'punycode';

const { Filter } = Shopware;

Filter.register('unicodeUri', (value) => {
    if (!value) {
        return '';
    }

    const unicode = Punycode.toUnicode(value);

    return decodeURI(unicode);
});
