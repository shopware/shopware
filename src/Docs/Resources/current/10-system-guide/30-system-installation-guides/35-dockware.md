[titleEn]: <>(Dockware)
[hash]: <>(article:dockware_installation)

##Dockware installation (managed docker setup for Shopware 6)

Start Shopware 6 in just a couple of seconds using dockware.io
It already comes with everything you need for a smooth development workflow.
This includes all available Shopware 6 versions, MySQL, Adminer, Mailcatcher,
easy PHP switching, XDebug, useful make commands and way more.

####1. Create docker-compose.yml
Create a new `docker-compose.yml` in the folder where you want to start your project and use
our template below.

Dockware does already come with an installed Shopware 6.
You can change the Shopware version along with the PHP version in your compose file.

Here's an overview about what versions are available: https://hub.docker.com/r/dockware/dev


```yaml
version: "3"

services:
        
    shopware:
      # use either tag "latest" or any other version like "6.1.5", ...
      image: dockware/dev:latest
      container_name: shopware
      ports:
         - "80:80"
         - "3306:3306"
         - "22:22"
         - "8888:8888"
         - "9999:9999"
      volumes:
         - "db_volume:/var/lib/mysql"
         - "shop_volume:/var/www/html"
      networks:
         - web
      environment:
         # default = 0, recommended to be OFF for frontend devs
         - XDEBUG_ENABLED=1
         # default = latest PHP, optional = specific version
         - PHP_VERSION=7.4

volumes:
  db_volume:
    driver: local
  shop_volume:
    driver: local

networks:
  web:
    external: false
```




####2. Start Docker
Open the folder with your compose file in your terminal
and execute this command to start your container:

```bash
docker-compose up -d
```


####3. Prepare Development
Now download the current version of Shopware to your host into a "src" directory.

This is required to have code completion and IntelliSense right in your IDE.


```bash
mkdir -p ./src
docker cp shopware:/var/www/html/. ./src
```



####4. Prepare IDE
Open the "src" folder with your preferred IDE and wait until finished loading.
Then add a new SFTP connection to your container. (We recommend Automatic-Upload if possible)

That's it, you're done and ready to develop your own plugins and projects.



Default credentials for dockware can be found at https://dockware.io/docs#default-credentials

For more information take a few minutes and check out our documentation
with lots of interesting details: https://dockware.io/docs#start-developing

