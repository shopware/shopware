export default function initializeConfig() {
    const configService = this.getContainer('service').configService;
    const loginService = this.getContainer('service').loginService;
    const context = this.getContainer('init').contextService;

    if (!loginService.isLoggedIn()) {
        return;
    }

    configService.getConfig().then((response) => {
        context.config = response;
    }).catch();
}
