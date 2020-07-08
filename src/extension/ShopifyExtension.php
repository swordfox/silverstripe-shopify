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
         // Create the product
         if ($product = $this->importObject(Product::class, $shopifyProduct)) {
             // If called from webhook, initiate $client
             if (!$client) {
                 try {
                     $client = new Client();
                 } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                     exit($e->getMessage());
                 } catch (\Exception $e) {
                     exit($e->getMessage());
                 }
             }

             $currentcollections = $product->Collections();
             $allcollections = $this->importCollects($client, $shopifyProduct->id);

             // Remove any collections that have been deleted.
             foreach ($currentcollections as $currentcollection) {
                 if (!in_array($currentcollection->ID, $allcollections)) {
                     $product->Collections()->remove($currentcollection);
                 }
             }

             $product->write();

             // Publish the product and it's connections
             $product->publishRecursive();
             self::log("[{$product->ID}] Updated product {$product->Title} and it's connections", self::SUCCESS);
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
                 $this->importCollection($shopifyCollection);
             }
         }
     }

     public function importCollection($shopifyCollection)
     {
         if ($shopifyCollection->published_scope == 'global' or $shopifyCollection->published_scope == 'web') {
             // Create the collection
             if ($collection = $this->importObject(Collection::class, $shopifyCollection)) {
                 // Publish the product and it's connections
                 $collection->publishRecursive();
                 self::log("[{$collection->ID}] Published collection {$collection->Title} and it's connections", self::SUCCESS);
             } else {
                 self::log("[{$shopifyCollection->id}] Could not create collection", self::ERROR);
             }
         }
     }

     /**
      * Import the Shopify Collects
      * @param Client $client
      *
      * @throws \SilverStripe\ORM\ValidationException
      */
     public function importCollects(Client $client, $product_id)
     {
         try {
             $collects = $client->collects($product_id);
         } catch (\GuzzleHttp\Exception\GuzzleException $e) {
             exit($e->getMessage());
         }

         $allcollections = [];

         if (($collects = $collects->getBody()->getContents()) && $collects = Convert::json2obj($collects)) {
             foreach ($collects->collects as $shopifyCollect) {
                 if (
                     ($collection = Collection::getByShopifyID($shopifyCollect->collection_id))
                     && ($product = Product::getByShopifyID($shopifyCollect->product_id))
                 ) {
                     $collection->Products()->add($product, [
                         'ShopifyID' => $shopifyCollect->id,
                         'SortValue' => $shopifyCollect->sort_value,
                         'Position' => $shopifyCollect->position,
                         'Featured' => $shopifyCollect->featured
                     ]);
                     self::log("[{$shopifyCollect->id}] Created collect between Product[{$product->ID}] and Collection[{$collection->ID}]", self::SUCCESS);

                     array_push($allcollections, $collection->ID);
                 }
             }
         }

         return $allcollections;
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
