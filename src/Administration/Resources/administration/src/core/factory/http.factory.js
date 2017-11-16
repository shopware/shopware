import Axios from 'axios';

export default function HTTPClient(context) {
    return createClient(context);
}

function createClient(context) {
    return Axios.create({
        baseURL: context.apiPath
    });
}
