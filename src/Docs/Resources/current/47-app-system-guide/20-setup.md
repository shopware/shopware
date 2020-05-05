[titleEn]: <>(Setup of app system)
[metaDescriptionEn]: <>(Here you can find all information you need concerning setup of apps)

## App system in Shopware platform

## Setup of your app

It's important to emphasize that you can define the endpoints for your apps - You have to run it yourself. 
So you have to make sure that your program runs somewhere, on a server you take care of. 

In order to write apps by yourself, you don't need lots of things concerning setup:
 
* A publicly accessible web server with a language of your choice (PHP, NodeJS, Golang)
* Optional other services you need for your app, e.g. MySQL, Redis. 
* An app that calls URLs on your web server for certain Shopware events
 
The advantage of this approach is that in case of errors, you no longer have to wait until all 
merchants using your plugin have installed the update. Since you are the one running the program, any 
customizations you make will directly affect all merchants using your app.

This is more work and more responsibility - but we can help you with that as well: 

In cooperation with platform.sh we plan to provide templates that will make it easier for you to get started 
with running apps. With just a few clicks you can run your environment on which you build extensions 
for Shopware in your favorite programming language.
