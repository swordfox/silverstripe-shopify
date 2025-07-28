# Simple shopify module for SilverStripe sites
This module is for users that want to implement there shopify products into a SilverStripe storefront.

Based on [xddesigners/silverstripe-shopify](https://github.com/xddesigners/silverstripe-shopify) - but completely reworked.

* Collections are added / removed on a per product basis.
* Import task with options: default, productsonly & productsall (See 'Set up import script' below).
* Buy Button scripts included in templates to make it easy to modify & implement in your own way.
* Versioning removed.
* Images are not imported from Shopify, instead we use [Images.weserv.nl](https://images.weserv.nl/) - An image cache & resize service.
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
If you want to use webhooks via the API e.g. dev/tasks/Swordfox-Shopify-Task-Webhooks/create you need to ensure 'webhooks_shared_secret' is the same as 'shared_secret', however if you want to use manually added webhooks, you will need to get your webhooks shared secret from `admin/settings/notifications`

### Config
```yaml
Swordfox\Shopify\Client:
  api_key: 'YOUR_API_KEY'
  api_password: 'YOUR_API_PASSWORD'
  api_limit: 50 # Default limit, 250 max
  api_version: '2021-01' # Default 2021-01
  storefront_access_token: 'YOUR_ACCESS_TOKEN' # for buybutton code
  shopify_domain: 'YOUR_SHOPIFY_DOMAIN' # mydomain.myshopify.com
  shared_secret: 'YOUR_API_SHARED_SECRET'
  webhooks_shared_secret: 'YOUR_WEBHOOKS_SHARED_SECRET' # Use same as above for webhooks added via API e.g. dev/tasks/Swordfox-Shopify-Task-Webhooks/create
  webhooks_create:
    'products/update': 'shop/webhook/update/product'
    'products/create': 'shop/webhook/update/product'
    'products/delete': 'shop/webhook/delete/product'
    'collections/create': 'shop/webhook/update/collection'
    'collections/update': 'shop/webhook/update/collection'
    'collections/delete': 'shop/webhook/delete/collection'
    'inventory_levels/connect': 'shop/webhook/update/inventory'
    'inventory_levels/update': 'shop/webhook/update/inventory'
  delete_on_shopify: false
  delete_on_shopify_after: '+3 days' # strtotime('+3 days')
  delete_on_shopify_keep_active: false
  hide_out_of_stock: false
  hide_if_no_image: false
  hide_if_collection_not_active: false
  new_based_on: 'Created' # LastEdited or ImageAdded (use with hide_if_no_image)
  new_timeframe: '+7 days' # strtotime('+7 days')
  cron_interval: '-18 hours' # Allow for timezone offset, e.g. if your timezone is +12:00, add your cron_interval to that as a negative value. So if your cron runs every 6 hours, set the cron_interval to '-18 hours'
  custom_metafields: true # product.metafields.custom.metatitle & product.metafields.custom.brand
  googlefeed_gtinbarcode: false # Show gtin as Barcode from Shopify
  googlefeed_mpnsku: true # Show mpn as SKU from Shopify
  googlefeed_condition: new # The condition of items
  googlefeed_storecode: 'code' # Show store_code in feed, e.g. Queenstown

# Override $default_sort
Swordfox\Shopify\Model\Product:
  default_sort: 'Created DESC' # LastEdited DESC or ImageAdded DESC
```

### Set up import script
You can run the import script manually trough the dev/tasks interface or set up up to run as a cron task. The default task is designed to run once per day and imports / updates the latest 50 `api_limit` from *admin/products.json*, *admin/custom_collections.json* & *admin/smart_collections.json*.
`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import` or `sake dev/tasks/Swordfox-Shopify-Task-Import`

The productsonly task is designed to run a few times a day if webhooks are not being used, it imports the latest 50 `api_limit` from *admin/products.json*.

`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import/productsonly` or `sake dev/tasks/Swordfox-Shopify-Task-Import/productsonly`

The productsall task is designed to run on initial set up and imports all from *admin/products.json*, *admin/custom_collections.json* & *admin/smart_collections.json*.

`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import/productsall` or `sake dev/tasks/Swordfox-Shopify-Task-Import/productsall`

### Set up webhooks
The following webhooks are supported and can be created automatically via the API using `http://example.com/dev/tasks/Swordfox-Shopify-Task-Webhooks/create` or `sake dev/tasks/Swordfox-Shopify-Task-Webhooks/create`

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
