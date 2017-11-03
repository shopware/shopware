export default function createLoginService(httpClient) {
    return {
        loginByUsername
    };

    function loginByUsername(user, pass) {
        return httpClient.post('auth', {
            username: user,
            password: pass
        });
    }
}

