[titleEn]: <>(Platform.sh infrastructure)
[metaDescriptionEn]: <>(This is all about the hosting on platformm.sh)
[hash]: <>(article:app_platform_sh_hosting)

## Infrastructure

Let's talk about the infrastructure.  
The infrastructure is coupled to your plan which you are paying for.  
Each environment / cluster has its own resources. You can't share them between multiple environments.  

There are three types of resources which you can configure.  
CPU, RAM and disk space.  
You can configure them in your `.platform.app.yaml` for your application and in your `.platform/services.yaml` for your services.  

### CPU and RAM

The resources for CPU and RAM are shared between all your container in the cluster.  
By default platform.sh decides for you how much resources each application and service needs.
  
However if you want to decide for yourself then you need to set the `size` key.  
By default this is set to `AUTO`.  
The options for this key are `S`, `M`, `L`, `XL`, `2XL` and `4XL`.  
They should be somewhat self-explanatory.
  
You should keep in mind that you can't exceed your plan limit.  
If the total resources requested by all your applications and services is larger than that what your plan size allows
then a production deployment will fail with an error.  

This also means that the `size` key has no impact on your development environment.  
The default `size` for a development environment is `S`.  
However if you need to increase it you can do this on your plan settings page for a fee.  

### Disc space

The resources for disc space are shared between all your container in the cluster.  
The key for this is `disk` and it is optional.  
If you don't set it then platform.sh will decide for you how much disc space each container needs.  

However if you want to define how much disc space your application and your database gets you simply need to set this key.  
The value of the key is always in MB. 

For example if you have 20GB free disc space which are 20480MB then you could give your application 5GB (5120MB)  
and your database the remaining 15GB (15360MB).  
