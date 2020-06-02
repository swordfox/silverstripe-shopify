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

use SilverStripe\ORM\DB;

/**
 * Class ShopifyExtension
 *
 * @author Bram de Leeuw
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

     public function addStarsToKeywords($keywords)
     {
         $keywords = trim($keywords);
         if (!$keywords) {
             return "";
         }

         $splitWords = explode(" ", $keywords);
         $newWords = [];

         do {
             $word = current($splitWords);
             if ($word[1] == '"') {
                 while (next($splitWords) !== false) {
                     $subword = current($splitWords);
                     $word .= ' ' . $subword;
                     if (substr($subword, -1) == '"') {
                         break;
                     }
                 }
             } else {
                 $word .= '*';
             }
             $newWords[] = $word;
         } while (next($splitWords) !== false);

         return implode(" ", $newWords);
     }

     /**
      * Import the shopify products
      * @param Client $client
      *
      * @throws \Exception
      */
     public function importProducts(Client $client, $since_id=0)
     {
         try {
             $products = $client->products($since_id);
         } catch (\GuzzleHttp\Exception\GuzzleException $e) {
             exit($e->getMessage());
         }

         if (($products = $products->getBody()->getContents()) && $products = Convert::json2obj($products)) {
             foreach ($products->products as $shopifyProduct) {
                 $this->importProduct($shopifyProduct);
             }

             // Disable cycle through all, using updated_at+desc
             /*
             if (count($products->products)) {
                 $this->importProducts($client, $shopifyProduct->id);
             }
             */
         }
     }

     public function importProduct($shopifyProduct)
     {
         $delete_on_shopify = Client::config()->get('delete_on_shopify');
         $delete_on_shopify_after = Client::config()->get('delete_on_shopify_after');

         // Create the product
         if ($product = $this->importObject(Product::class, $shopifyProduct)) {
             $currentimages = $product->Images();
             $allimages = [];

             // Create the images
             if (!empty($shopifyProduct->images)) {
                 $i = 0;
                 foreach ($shopifyProduct->images as $shopifyImage) {
                     array_push($allimages, $shopifyImage->src);

                     if ($i == 0) {
                         $product->OriginalSrc = $shopifyImage->src;
                     }

                     if (!$ExistingImage = ShopifyImage::get()->where("OriginalSrc='{$shopifyImage->src}'")->first()) {
                         if ($image = $this->importObject(ShopifyImage::class, $shopifyImage)) {
                             $product->Images()->add($image);
                         }
                     }

                     $i++;
                 }
             }

             // Remove any images that have been deleted.
             foreach ($currentimages as $currentimage) {
                 if (!in_array($currentimage->OriginalSrc, $allimages)) {
                     $product->Images()->remove($currentimage);
                 }
             }

             // Create the variants
             $currentvariants = $product->Variants();
             $allvariants = [];

             if (!empty($shopifyProduct->variants)) {
                 foreach ($shopifyProduct->variants as $shopifyVariant) {
                     // Delete if inventory_quantity = 0 and after 3 days based on updated_at 'I hope'
                     if (count($shopifyProduct->variants)==1 and $delete_on_shopify) {
                         if ($shopifyVariant->inventory_quantity == 0 and $shopifyVariant->inventory_management == 'shopify' and ($product->DeleteOnShopify == '0000-00-00' or $product->DeleteOnShopify == '')) {
                             $product->DeleteOnShopify = date('Y-m-d', strtotime($delete_on_shopify_after));
                         } elseif ($shopifyVariant->inventory_quantity > 0) {
                             $product->DeleteOnShopify = '0000-00-00';
                         }
                     }

                     array_push($allvariants, $shopifyVariant->id);

                     if ($variant = $this->importObject(ProductVariant::class, $shopifyVariant)) {
                         $product->Variants()->add($variant);
                     }
                 }
             }

             // Remove any variants that have been deleted.
             foreach ($currentvariants as $currentvariant) {
                 if (!in_array($currentvariant->ShopifyID, $allvariants)) {
                     $product->Variants()->remove($currentvariant);
                 }
             }

             // Create the tags
             $currenttags = $product->Tags();
             $alltags = [];

             if (!empty($product->Tags)) {
                 $shopifyTags = array_map('trim', explode(',', $product->Tags));

                 foreach ($shopifyTags as $shopifyTag) {
                     array_push($alltags, $shopifyTag);

                     if ($tag = $this->importObject(ProductTag::class, $shopifyTag)) {
                         $product->Tags()->add($tag);
                     }
                 }
             }

             // Remove any tags that have been deleted.
             foreach ($currenttags as $currenttag) {
                 if (!in_array($currenttag->Title, $alltags)) {
                     $product->Tags()->remove($currenttag);
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
         if ($shopifyCollection->published_scope == 'global') {
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
     public function importCollects(Client $client, $since_id=0, $product_id=0)
     {
         try {
             $collects = $client->collects($since_id, $product_id);
         } catch (\GuzzleHttp\Exception\GuzzleException $e) {
             exit($e->getMessage());
         }

         if (($collects = $collects->getBody()->getContents()) && $collects = Convert::json2obj($collects)) {
             if ($since_id==0 and $product_id==0) {
                 DB::query('TRUNCATE TABLE ShopifyCollection_Products;');
                 DB::query('ALTER TABLE ShopifyCollection_Products AUTO_INCREMENT = 0');
             }

             foreach ($collects->collects as $shopifyCollect) {
                 $this->importCollect($shopifyCollect);
             }

             if (count($collects->collects)) {
                 $this->importCollects($client, $shopifyCollect->id);
             }
         }
     }

     public function importCollect($shopifyCollect)
     {
         if (
             ($collection = Collection::getByShopifyID($shopifyCollect->collection_id))
             && ($product = Product::getByShopifyID($shopifyCollect->product_id))
         ) {
             /*
             $currentproducts = $collection->Products()->toArray();
             $allproducts = [];

             echo "<pre>";
             print_r($collection->Title);
             print_r($collection->Products()->toArray());
             echo "</pre>";
             */

             $collection->Products()->add($product, [
                 'ShopifyID' => $shopifyCollect->id,
                 'SortValue' => $shopifyCollect->sort_value,
                 'Position' => $shopifyCollect->position,
                 'Featured' => $shopifyCollect->featured
             ]);
             self::log("[{$shopifyCollect->id}] Created collect between Product[{$product->ID}] and Collection[{$collection->ID}]", self::SUCCESS);
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
