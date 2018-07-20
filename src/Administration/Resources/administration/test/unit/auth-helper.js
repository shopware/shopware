const AuthStore = Shopware.State.getStore('auth');

export default (username = 'admin', password = 'shopware') => {
    return AuthStore.loginUserWithPassword(username, password);
};
