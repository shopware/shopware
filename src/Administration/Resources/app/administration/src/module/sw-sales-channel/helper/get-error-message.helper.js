const { Utils } = Shopware;

export default {
    getErrorMessage
};

export function getErrorMessage(error) {
    const errorDetail = Utils.get(error, 'response.data.errors[0]', null);

    if (errorDetail && errorDetail.detail && errorDetail.status === 500) {
        console.error(errorDetail);
        return null;
    }

    if (typeof errorDetail.detail === 'object') {
        return errorDetail.detail.message;
    }

    return errorDetail.detail;
}
