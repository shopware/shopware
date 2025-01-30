import FormSerializeUtil from 'src/utility/form/form-serialize.util';

describe('FormSerializeUtil', () => {
    test('creates JSON object from FormData', () => {
        document.body.innerHTML = `
            <form class="test-form" id="test-form" method="POST" action="some/url">
                <input type="text" value="Airbus A330" name="plane-model">
                <input type="number" value="2006" name="plane-year">

                <!-- Test input with empty value -->
                <input type="number" value="" name="flight-number">

                <!-- Test input without name attribute. Must not be in the result -->
                <input type="text" value="Foo bar" placeholder="Enter text...">

                <select name="airline">
                    <option value="1">Cathay Pacific</option>
                    <option value="2">Vietnam Airlines</option>
                    <option value="3" selected>Lufthansa</option>
                </select>

                <!-- Checked checkbox must be in FormData -->
                <input type="checkbox" name="terms" required checked>

                <!-- Unchecked checkbox must not be in FormData -->
                <input type="checkbox" name="newsletter">

                <button type="button" id="btn">Submit</button>
            </form>
        `;

        const result = FormSerializeUtil.serializeJson(document.querySelector('#test-form'), true);

        expect(result).toEqual({
            'plane-model': 'Airbus A330',
            'plane-year': '2006',
            'flight-number': '',
            'airline': '3',
            'terms': 'on',
        });
    });

    test('will return an empty object when no FormData is empty', () => {
        document.body.innerHTML = `
            <form class="test-form" id="test-form" method="POST" action="some/url">
                <!-- Test input without name attribute. Must not be FormData -->
                <input type="text" value="Foo bar" placeholder="Enter text...">

                <!-- Unchecked checkbox must not be in FormData -->
                <input type="checkbox" name="newsletter">

                <button type="button" id="btn">Submit</button>
            </form>
        `;

        const result = FormSerializeUtil.serializeJson(document.querySelector('#test-form'), true);

        expect(result).toEqual({});
    });

    test('will throw an error when the given element is not a form and "strict" is true', () => {
        document.body.innerHTML = `
            <div class="test-form" id="test-form"></div>
        `;

        expect(() => {
            FormSerializeUtil.serializeJson(document.querySelector('#test-form'), true);
        }).toThrowError('The passed element is not a form!');
    });

    test('will return an empty object when the given element is not a form an "strict" is false', () => {
        document.body.innerHTML = `
            <div class="test-form" id="test-form"></div>
        `;

        const result = FormSerializeUtil.serializeJson(document.querySelector('#test-form'), false);
        expect(result).toEqual({});
    });
});
