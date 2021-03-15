<?php

namespace Swordfox\Shopify\Extension;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\View\Requirements;
use Swordfox\Shopify\Client;
use Swordfox\Shopify\Model\Collection;
use Swordfox\Shopify\Model\ShopifyImage;
use Swordfox\Shopify\Model\Product;
use Swordfox\Shopify\Model\ProductVariant;
use Swordfox\Shopify\Model\ProductTag;

/**
 * Class ShopifyExtension
 *
 * @author Graham McLellan
 * @package Swordfox\Shopify\Extension
 *
 * @property ShopifyExtension|\PageController $owner
 */

 class ShopifyExtension extends DataExtension
 {
     const NOTICE = 0;
     const SUCCESS = 1;
     const WARN = 2;
     const ERROR = 3;

     /**
      * Import the shopify products
      * @param Client $client
      *
      * @throws \Exception
      */
     public function importProducts(Client $client, $all=false, $since_id=0)
     {
         try {
             $products = $client->products($since_id, $all);
         } catch (\GuzzleHttp\Exception\GuzzleException $e) {
             exit($e->getMessage());
         }

         if (($products = $products->getBody()->getContents()) && $products = Convert::json2obj($products)) {
             foreach ($products->products as $shopifyProduct) {
                 $this->importProduct($shopifyProduct, $client);
             }

             if (count($products->products) and $all) {
                 $this->importProducts($client, $all, $shopifyProduct->id);
             }
         }
     }

     public function importProduct($shopifyProduct, $client=null)
     {
         $hide_if_no_image = Client::config()->get('hide_if_no_image');
         $delete_on_shopify = Client::config()->get('delete_on_shopify');
         $delete_on_shopify_after = Client::config()->get('delete_on_shopify_after');

         // Create the product
         if ($product = $this->importObject(Product::class, $shopifyProduct)) {
             // If $hide_if_no_image and no images then don't update connections
             if($client or ($hide_if_no_image and !$product->OriginalSrc)){
                 self::log("[{$product->ID}] Updated product {$product->Title}", self::SUCCESS);
             }elseif($product->New){
                 // If called from webhook, initiate $client & update connections
                 try {
                     $client = new Client();
                 } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                     exit($e->getMessage());
                 } catch (\Exception $e) {
                     exit($e->getMessage());
                 }

                 $this->importCollections($client, 'custom_collections');
                 $this->importCollections($client, 'smart_collections');

                 // Publish the product and it's connections
                 $product->publishRecursive();
                 self::log("[{$product->ID}] Updated product {$product->Title} and it's connections", self::SUCCESS);
             }

         } else {
             self::log("[{$shopifyProduct->id}] Could not create product", self::ERROR);
         }
     }

     /**
      * Import the SHopify Collections
      * @param Client $client
      *
      * @throws \SilverStripe\ORM\ValidationException
      */
     public function importCollections(Client $client, $type)
     {
         try {
             $collections = $client->collections($type);
         } catch (\GuzzleHttp\Exception\GuzzleException $e) {
             exit($e->getMessage());
         }

         if (($collections = $collections->getBody()->getContents()) && $collections = Convert::json2obj($collections)) {
             foreach ($collections->{$type} as $shopifyCollection) {
                 $this->importCollection($client, $shopifyCollection);
             }
         }
     }

     public function importCollection(Client $client, $shopifyCollection)
     {
         if ($shopifyCollection->published_scope == 'global' or $shopifyCollection->published_scope == 'web') {
             // Create the collection
             if ($collection = $this->importObject(Collection::class, $shopifyCollection)) {
                 // Publish the product and it's connections
                 $collection->publishRecursive();
                 self::log("[{$collection->ID}] Published collection {$collection->Title} and it's connections", self::SUCCESS);

                 $currentproducts = $collection->Products();
                 $allproducts = [];

                 $methodUri = 'admin/api/'.$client->api_version.'/products.json?collection_id='.$collection->ShopifyID.'&updated_at_min='.date(DATE_ATOM, strtotime('-1 day')).'&fields=id,title&limit=50';

                 do {
                    $products = $client->collectionProducts($methodUri);
                    $productsHeaderLink = $products->getHeader('Link');

                    if (($products = $products->getBody()->getContents()) && $products = Convert::json2obj($products)) {
                        echo count($products->products);
                        foreach ($products->products as $shopifyProduct) {
                            print_r($shopifyProduct);
                            if ($product = Product::getByShopifyID($shopifyProduct->id)) {
                                $collection->Products()->add($product);

                                array_push($allproducts, $product->ID);
                            }
                         }
                     }

                     $methodUri = null;
                     if (!is_null($productsHeaderLink) && count($productsHeaderLink) == 1) {
                         $link = $productsHeaderLink[0];

                         if(strlen($link) > 0 && strpos($productsHeaderLink[0], '>; rel="next"') > 0){
                             $strposrelnext = strpos($productsHeaderLink[0], '>; rel="next"');
                             if(strlen($link) > 0 && strpos($productsHeaderLink[0], '>; rel="previous"') > 0){
                                 $strposrelprev = strpos($productsHeaderLink[0], '>; rel="previous"');

                                 $methodUri = trim(substr($productsHeaderLink[0], $strposrelprev+20, -13));
                             }else{
                                 $methodUri = trim(substr($productsHeaderLink[0], 1, -13));
                             }
                         }
                     }

                 } while (!is_null($methodUri) && strlen($methodUri) > 0);

                 // Remove any collections that have been deleted.
                 foreach ($currentproducts as $currentproduct) {
                     if (!in_array($currentproduct->ID, $allproducts)) {
                         $collection->Products()->remove($currentproduct);
                     }
                 }

                 $collection->write();

             } else {
                 self::log("[{$shopifyCollection->id}] Could not create collection", self::ERROR);
             }
         }
     }

     /**
      * Import the base product
      *
      * @param Product|ProductVariant|Image|string $class
      * @param $shopifyData
      * @return null|Product|ProductVariant|Image
      */
     public function importObject($class, $shopifyData)
     {
         $object = null;
         try {
             $object = $class::findOrMakeFromShopifyData($shopifyData);
             self::log("[{$object->ID}] Created {$class} {$object->Title}", self::SUCCESS);
         } catch (\Exception $e) {
             self::log($e->getMessage(), self::ERROR);
         } catch (\GuzzleHttp\Exception\GuzzleException $e) {
             self::log("[Guzzle error] {$e->getMessage()}", self::ERROR);
         }

         return $object;
     }

     public function deleteProducts($client)
     {
         if ($products = Product::get()->where('DeleteOnShopify=CURDATE()')) {
             foreach ($products as $product) {
                 $product_id = $product->ShopifyID;

                 try {
                     $client->deleteProduct($product_id);
                 } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                     exit($e->getMessage());
                 }

                 self::log("[{$product->ID}] Deleted product '{$product->Title}'", self::SUCCESS);
             }
         }
     }

     /**
      * Log messages to the console or cron log
      *
      * @param $message
      * @param $code
      */
     public static function log($message, $code = self::NOTICE)
     {
         switch ($code) {
             case self::ERROR:
                 echo "[ ERROR ] {$message}\n";
                 break;
             case self::WARN:
                 echo "[WARNING] {$message}\n";
                 break;
             case self::SUCCESS:
                 echo "[SUCCESS] {$message}\n";
                 break;
             case self::NOTICE:
             default:
                 echo "[NOTICE ] {$message}\n";
                 break;
         }
     }

     /**
      * Loop the given data map and possible sub maps
      *
      * @param array $map
      * @param $object
      * @param $data
      */
     public static function loop_map($map, &$object, $data)
     {
         foreach ($map as $from => $to) {
             if (is_array($to) && is_object($data->{$from})) {
                 self::loop_map($to, $object, $data->{$from});
             } elseif (isset($data->{$from}) && $value = $data->{$from}) {
                 $object->{$to} = $value;
             }
         }
     }
 }
