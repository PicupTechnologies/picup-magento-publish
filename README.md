[logo]: ./picup-logo.png "Picup"
![Picup Logo][logo]

#Picup Shipping - Magento2 Plugin

The Picup Shipping module has been tested on Magento 2.3.4 and higher and will be continuously maintained to support newer versions of magento.

## Installation

The recommended installation is to install the plugin using composer but a manual installation is possible with some experience.

### Composer Installation

```
composer require pickup/shipping
```

### Manual Installation

```
git clone https://github.com/PicupTechnologies/picup-magento-publish.git
```
Copy the Picup folder from the respository to your Magento2 webroot and place the files
under app/code

### Post Installation

From your website root folder you should run the following to make sure the plugin installed correctly

```
bin/magento setup:upgrade
bin/magento setup:static-content:deploy --force
```

### Overview

## Picup Setup

You need to register for a Picup account and retrieve your API keys so you can integrate your store.


## Store Setup

## Shift Setup

## Product Setup

