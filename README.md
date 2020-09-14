[logo]: ./picup-logo.png "Picup"
![Picup Logo][logo]

#Picup Shipping - Magento2 Plugin

The Picup Shipping module has been tested on Magento 2.3.4 and higher and will be continuously maintained to support newer versions of magento.

## Installation

The recommended installation is to install the plugin using composer but a manual installation is possible with some experience.

### Composer Installation

```
composer require picup/shipping
```

### Manual Installation

```
git clone https://github.com/PicupTechnologies/picup-magento-publish.git
```

Copy the Picup folder from the code respository to your Magento2 webroot and place the files under 

```<webroot>app/code/Picup/Shipping```

### Post Installation

From your website root folder you should run the following to make sure the plugin installed correctly

```
bin/magento module:enable Picup_Shipping
bin/magento setup:upgrade
bin/magento setup:static-content:deploy --force
bin/magento cache:clean
bin/magento cache:flush
```

### Overview
Once your installation is complete and you have flushed your caches you should see the Picup module in your backend

[picup-setup]: ./images/picup-screen.png "Picup Screen"
![Picup Screen][picup-setup]

## Picup Setup
You need to register for a Picup account on our [website](https://picup.co.za) and retrieve your API keys so you can integrate your store.

### Store Setup
Click on the store settings link in the Magento plugin and it will take you to your default store's shipping settings where you can enter your API credentials.

[store-setting]: ./images/store-settings.png "Store Settings"
![Store Settings][store-setting]

#### Important Settings

- Enabled - Make sure this is enabled for our plugin to work
- On Demand Shipping - enables a once off quote along with your shift pricing
- Warehouse ID - If you have multiple stores you can allocate different warehouses to each store 
- Test Mode - Make sure this is Off when you go to production

### Buckets & Shift Setup

[adding-shifts]: ./images/adding-shifts.png "Adding Shifts for Buckets"
![Adding Shifts][adding-shifts]

- Setup shifts for each day with the price that you want for that shift.  
- Shift times on the same day can overlap and will work as long as the start and end times are configured correctly.
- Give your shifts good descriptions as that will be displayed to the customers on checkout.

### Product Setup

[product-setup]: ./images/product-setup.png "Product"
![Product Setup][product-setup]

- Each product can be tied in with your Picup deliveries, we rely on the product dimensions to give the most accurate quotes.
- You can also tie a product in with certain shifts so those appear on the cart checkout.

### Uninstall

Removing our plugin is as easy as the installation

```
composer remove picup/shipping
bin/magento setup:upgrade
bin/magento cache:clean
bin/magento cache:flush
```

After this you can remove the tables which have the picup_ prefix in your database if you don't plan to reinstall.



