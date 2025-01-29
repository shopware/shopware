import FormSerializeUtil from 'src/utility/form/form-serialize.util';

describe('FormSerializeUtil', () => {
    test('creates JSON object', () => {
        document.body.innerHTML = `
            <form class="test-form" id="test-form" method="POST" action="some/url">
                <label for="plane-model">Plane model</label>
                <input type="text" value="Airbus" id="plane-model" name="plane-model">

                <label for="plane-year">Model year</label>
                <input type="number" value="1998" id="plane-year" name="plane-year">

                <button type="button" id="btn">Submit</button>
            </form>
        `;

        const result = FormSerializeUtil.serializeJson(document.querySelector('#test-form'), true);

        // TODO: Fix the serializeJson method!
        expect(result).toBe({});
    });
});
