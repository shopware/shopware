FROM gitpod/workspace-full:latest

RUN sudo apt-get update && \
    sudo apt-get install -y php8.1-fpm rsync && \
    brew install symfony-cli/tap/symfony-cli FriendsOfShopware/tap/shopware-cli
