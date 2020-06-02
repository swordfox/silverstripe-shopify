# Simple shopify module for SilverStripe sites
This module is for users that want to implement there shopify products into a SilverStripe storefront.

Based on [xddesigners/silverstripe-shopify](https://github.com/xddesigners/silverstripe-shopify) - but completely reworked.

* Collections are added / removed on a per product basis.
* Import task with options: default, productsonly & productsall (See 'Set up import script' below).
* Buy Button scripts included in templates to make it easy to modify & implement in your own way.
* Optional: Webhooks from Shopify to keep your store updated without having to rely on the import task which can be resource consuming (See 'Set up webhooks' below).
* Optional: delete_on_shopify for shops that want to delete their products from Shopify after they have been sold, useful for shops that have one-off products (See 'Set up delete_on_shopify' below).

It makes use of the [Shopify Buy Button](https://www.shopify.com/buy-button) to create the cart and checkout interface.   
You'll end up with a import job that fetches all the products and variants and stores them as Product DataObject in your site.

## Requirements
* SilverStripe 4.x

## Installation
Install the module trough composer and configure the api keys.  
```
composer require swordfox/silverstripe-shopify
```

Get up your api keys by creating a new Private App in your Shopify admin interface.
If you want to use webhooks, you will need to get your webhooks shared secret from `admin/settings/notifications`

### Config
```yaml
Swordfox\Shopify\Client:
  api_key: 'YOUR_API_KEY'
  api_password: 'YOUR_API_PASSWORD'
  api_limit: 50 # Default limit, 250 max
  storefront_access_token: 'YOUR_ACCESS_TOKEN'
  shopify_domain: 'YOUR_SHOPIFY_DOMAIN' # mydomain.myshopify.com
  shared_secret: 'YOUR_API_SHARED_SECRET'
  webhooks_shared_secret: 'YOUR_WEBHOOKS_SHARED_SECRET'
  delete_on_shopify: false
  delete_on_shopify_after: '+3 days' # strtotime('+3 days')
  hide_out_of_stock: false
```

### Set up import script
You can run the import script manually trough the dev/tasks interface or set up up to run as a cron task. The default task is designed to run once per day and imports / updates the latest 50 `api_limit` from *admin/products.json*, *admin/custom_collections.json* & *admin/smart_collections.json*.
`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import` or `sake dev/tasks/Swordfox-Shopify-Task-Import`

The productsonly task is designed to run a few times a day if webhooks are not being used, it imports the latest 50 `api_limit` from *admin/products.json*.

`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import/productsonly` or `sake dev/tasks/Swordfox-Shopify-Task-Import/productsonly`

The productsall task is designed to run on initial set up and imports all from *admin/products.json*, *admin/custom_collections.json* & *admin/smart_collections.json*.

`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import/productsall` or `sake dev/tasks/Swordfox-Shopify-Task-Import/productsall`

### Set up webhooks
The following webhooks are supported.

* Collection creation 	https://www.example.com/shop-page/webhook/update/collection
* Collection deletion 	https://www.example.com/shop-page/webhook/delete/collection
* Collection update 	https://www.example.com/shop-page/webhook/update/collection
* Inventory level connect 	https://www.example.com/shop-page/webhook/update/inventory
* Inventory level update 	https://www.example.com/shop-page/webhook/update/inventory
* Product creation 	https://www.example.com/shop-page/webhook/update/product
* Product deletion 	https://www.example.com/shop-page/webhook/delete/product
* Product update 	https://www.example.com/shop-page/webhook/update/product

![Shopify webhooks](/readme/webhooks.png)

### Set up delete_on_shopify
You can run the delete on Shopify script manually trough the dev/tasks interface or set up up to run as a cron task. This task is useful for stores that sell one-off products and want to delete the products off Shopify after a certain period of time `delete_on_shopify_after: '+3 days'` which is set on ShopifyProduct::DeleteOnShopify during the import tasks if `delete_on_shopify: true`
`http://example.com/dev/tasks/Swordfox-Shopify-Task-DeleteOnShopify` or `sake dev/tasks/Swordfox-Shopify-Task-DeleteOnShopify`
