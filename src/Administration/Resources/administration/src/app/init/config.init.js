export default function initializeConfigDecorator() {
    const configService = this.getContainer('service').configService;
    const loginService = this.getContainer('service').loginService;
    const context = this.getContainer('init').contextService;

    this.addInitializerDecorator('worker', (service) => {
        function getConfig() {
            return configService.getConfig().then((response) => {
                context.config = response;
                service();
            }).catch(() => {
                loginService.logout();
            });
        }
        if (loginService.isLoggedIn()) {
            getConfig();
            return;
        }

        loginService.addOnLoginListener(getConfig);
    });
}
