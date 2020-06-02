# Simple shopify module for SilverStripe sites
This module is for users that want to implement there shopify products into a SilverStripe storefront.
It makes use of the [Shopify Buy Button](https://www.shopify.com/buy-button) to create the cart and checkout interface.   
You'll end up with a import job that fetches all the products and variants and stores them as Product DataObject in your site.


## Installation
Install the module trough composer and configure the api keys.  
`composer require swordfox/silverstripe-shopify`

Get up your api keys by creating a new Private App in your Shopify admin interface.

### Config
```yaml
Swordfox\Shopify\Client:
  api_key: 'YOUR_API_KEY'
  api_password: 'YOUR_API_PASSWORD'
  storefront_access_token: 'YOUR_ACCESS_TOKEN'
  shopify_domain: 'YOUR_SHOPIFY_DOMAIN' # mydomain.myshopify.com
  shared_secret: 'YOUR_API_SHARED_SECRET'
  webhooks_shared_secret: 'YOUR_WEBHOOKS_SHARED_SECRET'
  delete_on_shopify: false
  delete_on_shopify_after: '+3 days' # strtotime('+3 days')
  hide_out_of_stock: false
```

### Set up import script
You can run the import script manually trough the dev/tasks interface or set up up to run as a cron task.
`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import` or `sake dev/tasks/Swordfox-Shopify-Task-Import`

`http://example.com/dev/tasks/Swordfox-Shopify-Task-Import/productsonly` or `sake dev/tasks/Swordfox-Shopify-Task-Import/productsonly`



### Set up delete on Shopify script
You can run the import script manually trough the dev/tasks interface or set up up to run as a cron task.
`http://example.com/dev/tasks/Swordfox-Shopify-Task-DeleteOnShopify` or `sake dev/tasks/Swordfox-Shopify-Task-DeleteOnShopify`
