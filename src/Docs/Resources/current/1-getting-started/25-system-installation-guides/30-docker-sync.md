[titleEn]: <>(Using docker-sync)


## Using native mounting with Docker Volumes and docker-sync

If you are working on MacOS X and have to face with performance issues; or you want to use native mounting with Docker [Volumes](https://docs.docker.com/storage/volumes/) instead of [bind mounts](https://docs.docker.com/storage/bind-mounts/), but sharing your code into containers will slow down the code-execution. Thankfully, there is a solution for these problems, that's to use `docker-sync`    

### Preparation

Download & install `docker-sync` from [http://docker-sync.io/](http://docker-sync.io/) which supported for OSX, Windows, Linux and FreeBSD.

* For OSX, see [OSX](https://docker-sync.readthedocs.io/en/latest/getting-started/installation.html#installation-osx).
* For Windows, see [Windows](https://docker-sync.readthedocs.io/en/latest/getting-started/installation.html#installation-windows).
* For Linux, see [Linux](https://docker-sync.readthedocs.io/en/latest/getting-started/installation.html#installation-linux).
* See the list of alternatives at [Alternatives](https://docker-sync.readthedocs.io/en/latest/miscellaneous/alternatives.html)


### Enable to use docker-sync in PSH Console

Normally PSH will disable to use `docker-sync` as default. To switch to Docker Volumes and Docker Sync, you can simply update `DOCKER_SYNC_ENABLED: true` in your `.psh.yaml.override`. For example, mine looks like this

```yaml
const:
  #..
  DOCKER_SYNC_ENABLED: true
```

That's it. Then you could continue to install Shopware 6 as usual

1. Build and start the containers:

    ```bash
    > ./psh.phar docker:start
    ```

> This creates and starts the containers, watchers and the sync itself. Running start the first time will take several minutes to complete, but it will be a lot faster in the next times, since containers and volumes are reused.

2. Access the application container:

    ```bash
    > ./psh.phar docker:ssh
    ```

3. Execute the installer inside the docker container:

    ```bash
    > ./psh.phar install 
    ```

For more information about Shopware Installation, have a look [here](https://docs.shopware.com/en/shopware-platform-dev-en/getting-started/system-installation-guides)

### Next: [Startup](./../30-startup-guide/__categoryInfo.md)
  