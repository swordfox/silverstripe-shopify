<?php

namespace Swordfox\Shopify\Extension;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\View\Requirements;
use Swordfox\Shopify\Client;
use Swordfox\Shopify\Model\Collection;
use Swordfox\Shopify\Model\ShopifyImage;
use Swordfox\Shopify\Model\Product;
use Swordfox\Shopify\Model\ProductVariant;
use Swordfox\Shopify\Model\ProductTag;
use Swordfox\Shopify\Model\ShippingZone;
use Swordfox\Shopify\Model\ShippingCountry;

/**
 * Class ShopifyExtension
 *
 * @author  Graham McLellan
 * @package Swordfox\Shopify\Extension
 *
 * @property ShopifyExtension|\PageController $owner
 */

class ShopifyExtension extends Extension
{
    const NOTICE = 0;
    const SUCCESS = 1;
    const WARN = 2;
    const ERROR = 3;

    /**
     * Import the shopify products
     *
     * @param Client $client
     *
     * @throws \Exception
     */
    public function importProducts(Client $client, $all = false, $since_id = 0)
    {
        $custom_metafields = Client::config()->get('custom_metafields');

        try {
            $products = $client->products();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        $body = $products->getBody()->getContents();

        if ($body && ($products = json_decode($body))) {
            foreach ($products->products as $shopifyProduct) {
                if ($custom_metafields) {
                    $productmetafields = $client->productmetafields($shopifyProduct->id);

                    $productMetaBody = $productmetafields->getBody()->getContents();

                    if ($productmetafields = json_decode($productMetaBody)) {
                        if (count($productmetafields->metafields)) {
                            foreach ($productmetafields->metafields as $metafield) {
                                if ($metafield->namespace == 'custom' and $metafield->key == 'brand' and $metafield->value) {
                                    $shopifyProduct->custom_brand = $metafield->value;
                                } elseif ($metafield->namespace == 'custom' and $metafield->key == 'metatitle' and $metafield->value) {
                                    $shopifyProduct->custom_metatitle = $metafield->value;
                                }
                            }
                        }
                    }
                }

                $this->importProduct($shopifyProduct, $client);
            }
        }
    }

    /**
     * Import the shopify products
     *
     * @param Client $client
     *
     * @throws \Exception
     */
    public function importProductsAll(Client $client)
    {
        $methodUri = 'admin/api/' . $client->api_version . '/products.json?limit=250';

        do {
            $products = $client->paginationCall($methodUri);
            $headerLink = $products->getHeader('Link');

            $body = $products->getBody()->getContents();

            if ($products = json_decode($body)) {
                foreach ($products->products as $shopifyProduct) {
                    $this->importProduct($shopifyProduct, $client);
                }
            }

            $methodUri = $this->methodUri($headerLink);
        } while (!is_null($methodUri) && strlen($methodUri) > 0);
    }

    /**
     * Import a shopify product
     *
     * @param Client $client
     *
     * @throws \Exception
     */
    public function importProductsSingle(Client $client, $product_id)
    {
        $product = $client->product($product_id);

        $body = $product->getBody()->getContents();

        if ($product = json_decode($body)) {
            $this->importProduct($product->product, $client);
        }
    }

    public function importProduct($shopifyProduct, $client = null)
    {
        $hide_if_no_image = Client::config()->get('hide_if_no_image');
        $delete_on_shopify = Client::config()->get('delete_on_shopify');
        $delete_on_shopify_after = Client::config()->get('delete_on_shopify_after');

        // Create the product
        if ($product = $this->importObject(Product::class, $shopifyProduct)) {
            // If $hide_if_no_image and no images then don't update connections
            if ($hide_if_no_image and !$product->OriginalSrc) {
                // Publish the product and it's connections
                $product->publishRecursive();
                self::log("[{$product->ID}] Updated product {$product->Title}", self::SUCCESS);
            } else {
                // If called from webhook, initiate $client & update connections
                try {
                    $client = new Client();
                } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                    exit($e->getMessage());
                } catch (\Exception $e) {
                    exit($e->getMessage());
                }

                // Get Collects for custom_collections only
                $currentcollections = $product->Collections();
                $allcollections = $this->importCollects($client, $shopifyProduct->id);

                // Remove any collections that have been deleted.
                foreach ($currentcollections as $currentcollection) {
                    if (!in_array($currentcollection->ID, $allcollections)) {
                        $product->Collections()->remove($currentcollection);
                    }
                }

                $product->write();

                if ($product->New) {
                    // Maybe do this???
                    $this->importCollections($client, 'smart_collections', '-1 hour');
                }

                // Publish the product and it's connections
                $product->publishRecursive();
                self::log("[{$product->ID}] Updated product {$product->Title} and it's connections", self::SUCCESS);
            }
        } else {
            self::log("[{$shopifyProduct->id}] Could not create product", self::ERROR);
        }
    }

    /**
     * Import the Shopify Collections
     *
     * @param Client $client
     *
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function importCollections(Client $client, $type, $updatedatmin = false)
    {
        try {
            $collections = $client->collections($type);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        $body = $collections->getBody()->getContents();

        if ($body && ($collections = json_decode($body))) {
            foreach ($collections->{$type} as $shopifyCollection) {
                $this->importCollection($shopifyCollection, $client, $updatedatmin);
            }
        }
    }

    public function importCollection($shopifyCollection, $client = null, $updatedatmin = false)
    {
        if ($shopifyCollection->published_scope == 'global' or $shopifyCollection->published_scope == 'web') {
            // Create the collection
            if ($collection = $this->importObject(Collection::class, $shopifyCollection)) {
                // Publish the product and it's connections
                $collection->publishRecursive();
                self::log("[{$collection->ID}] Published collection {$collection->Title} and it's connections", self::SUCCESS);

                if (!$client) {
                    // If called from webhook, initiate $client & update connections
                    try {
                        $client = new Client();
                    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                        exit($e->getMessage());
                    } catch (\Exception $e) {
                        exit($e->getMessage());
                    }
                }

                $currentproducts = $collection->Products();
                $allproducts = [];

                $methodUri = 'admin/api/' . $client->api_version . '/products.json?collection_id=' . $collection->ShopifyID . '&limit=250' . ($updatedatmin ? ('&updated_at_min=' . date(DATE_ATOM, strtotime($updatedatmin))) : '');

                do {
                    $products = $client->paginationCall($methodUri);
                    $headerLink = $products->getHeader('Link');

                    $productsBody = $products->getBody()->getContents();

                    if ($products = json_decode($productsBody)) {
                        foreach ($products->products as $shopifyProduct) {
                            if ($product = Product::getByShopifyID($shopifyProduct->id)) {
                                $collection->Products()->add($product);

                                array_push($allproducts, $product->ID);
                            }
                        }
                    }

                    $methodUri = $this->methodUri($headerLink);
                } while (!is_null($methodUri) && strlen($methodUri) > 0);

                if (!$updatedatmin) {
                    // Remove any collections that have been deleted.
                    foreach ($currentproducts as $currentproduct) {
                        if (!in_array($currentproduct->ID, $allproducts)) {
                            $collection->Products()->remove($currentproduct);
                        }
                    }
                }

                $collection->write();
            } else {
                self::log("[{$shopifyCollection->id}] Could not create collection", self::ERROR);
            }
        }
    }

    public function importShippingZone($shopifyShippingZone, $client = null)
    {

        // Create the collection
        if ($shippingZone = $this->importObject(ShippingZone::class, $shopifyShippingZone)) {
            // Publish the product and it's connections
            $shippingZone->publishRecursive();
            self::log("[{$shippingZone->ID}] Published shippingZone {$shippingZone->Name}", self::SUCCESS);

            $shippingZone->write();

            return $shippingZone;
        } else {
            self::log("[{$shopifyShippingZone->id}] Could not create shipping zone", self::ERROR);
        }
    }


    /**
     * Import the Shopify Collects
     *
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

        $body = $collects->getBody()->getContents();

        if ($body && ($collects = json_decode($body))) {
            foreach ($collects->collects as $shopifyCollect) {
                if (($collection = Collection::getByShopifyID($shopifyCollect->collection_id))
                    && ($product = Product::getByShopifyID($shopifyCollect->product_id))
                ) {
                    $collection->Products()->add(
                        $product,
                        [
                            'ShopifyID' => $shopifyCollect->id,
                            'SortValue' => $shopifyCollect->sort_value,
                            'Position' => $shopifyCollect->position
                        ]
                    );
                    self::log("[{$shopifyCollect->id}] Created collect between Product[{$product->ID}] and Collection[{$collection->ID}]", self::SUCCESS);

                    array_push($allcollections, $collection->ID);
                }
            }
        }

        return $allcollections;
    }

    /**
     * Import the shopify inventory_levels
     *
     * @param Client $client
     *
     * @throws \Exception
     */
    public function updateInventoryLocation(Client $client)
    {
        $Count = 0;

        do {
            $PreviousCount = $Count;
            $ProductVariants = ProductVariant::get()->filter(['Location' => 0])->where('Inventory > 0');

            $Count = $ProductVariants->count();

            $i = 0;
            $InventoryItemIDs = '';
            foreach ($ProductVariants as $ProductVariant) {
                if ($ProductVariant->InventoryItemID) {
                    $InventoryItemIDs .= $ProductVariant->InventoryItemID . ',';

                    $i++;
                }

                if ($i == 50) {
                    break;
                }
            }

            if ($InventoryItemIDs != '') {
                $InventoryItemIDs = substr($InventoryItemIDs, 0, -1);

                $methodUri = 'admin/api/' . $client->api_version . '/inventory_levels.json?inventory_item_ids=' . $InventoryItemIDs . '&limit=250';

                $inventory_levels = $client->paginationCall($methodUri);
                //$headerLink = $inventory_levels->getHeader('Link');

                $inventory_levelsBody = $inventory_levels->getBody()->getContents();

                if ($inventory_levels = json_decode($inventory_levelsBody)) {                    foreach ($inventory_levels->inventory_levels as $inventory_level) {
                        if ($ProductVariant = ProductVariant::get()->filter(['InventoryItemID' => $inventory_level->inventory_item_id])->first() and $inventory_level->available > 0) {
                            $ProductVariant->Location = $inventory_level->location_id;
                            $ProductVariant->write();

                            self::log("[{$ProductVariant->ID}] Inventory Item updated '{$inventory_level->inventory_item_id}'", self::SUCCESS);
                        }
                    }
                }

                //$methodUri = $this->methodUri($headerLink);
            }
        } while ($PreviousCount != $Count);
    }

    /**
     * Import the shopify shipping_zones
     *
     * @param Client $client
     *
     * @throws \Exception
     */
    public function updateShipping(Client $client)
    {
        $currentShippingZones = ShippingZone::get();
        $activeShippingZones = [];

        $methodUri = 'admin/api/' . $client->api_version . '/shipping_zones.json';

        do {
            $shippingZones = $client->paginationCall($methodUri);
            $headerLink = $shippingZones->getHeader('Link');

            $shippingZonesBody = $shippingZones->getBody()->getContents();

            if ($shippingZones = json_decode($shippingZonesBody)) {
                //print_r($shippingZones);
                foreach ($shippingZones->shipping_zones as $shippingZone) {
                    $activeShippingZone = $this->importShippingZone($shippingZone, $client);
                    $activeShippingZones[] = $activeShippingZone->ID;
                }
            }

            $methodUri = $this->methodUri($headerLink);
        } while (!is_null($methodUri) && strlen($methodUri) > 0);

        // Remove Shipping Zones that no longer exist.
        foreach ($currentShippingZones as $currentShippingZone) {
            if (!in_array($currentShippingZone->ID, $activeShippingZones)) {
                $currentShippingZone->delete();
            }
        }
    }

    public function methodUri($headerLink)
    {
        $methodUri = null;
        if (!is_null($headerLink) && count($headerLink) == 1) {
            $link = $headerLink[0];

            if (strlen($link) > 0 && strpos($headerLink[0], '>; rel="next"') > 0) {
                $strposrelnext = strpos($headerLink[0], '>; rel="next"');
                if (strlen($link) > 0 && strpos($headerLink[0], '>; rel="previous"') > 0) {
                    $strposrelprev = strpos($headerLink[0], '>; rel="previous"');

                    $methodUri = trim(substr($headerLink[0], $strposrelprev + 20, -13));
                } else {
                    $methodUri = trim(substr($headerLink[0], 1, -13));
                }
            }
        }

        return $methodUri;
    }

    /**
     * Import the base product
     *
     * @param  Product|ProductVariant|Image|string $class
     * @param  $shopifyData
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
        if ($products = Product::get()->where("DeleteOnShopify <= CURDATE() AND DeleteOnShopify != '0000-00-00' AND DeleteOnShopify IS NOT NULL AND DeleteOnShopifyDone = 0")) {
            foreach ($products as $product) {
                $product_id = $product->ShopifyID;

                try {
                    $client->deleteProduct($product_id);

                    $product->DeleteOnShopifyDone = 1;
                    $product->write();
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
