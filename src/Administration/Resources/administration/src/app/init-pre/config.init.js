export default function initializeConfigDecorator() {
    return this.addInitializerDecorator('worker', (service) => {
        const configService = this.getContainer('service').configService;
        const loginService = this.getContainer('service').loginService;
        const context = this.getContainer('init').contextService;

        function getConfig() {
            return configService.getConfig().then((response) => {
                context.config = response;
                service();
            });
        }
        if (loginService.isLoggedIn()) {
            getConfig().catch();
            return;
        }

        loginService.addOnLoginListener(getConfig);
    });
}
