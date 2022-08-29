<?php

namespace Swordfox\Shopify\Model;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
//use SilverStripe\Versioned\Versioned;
use Swordfox\Shopify\Task\Import;

/**
 * Class ProductVariant
 *
 * @author  Graham McLellan
 * @package Swordfox\Shopify\Model
 *
 * @property string Title
 * @property string ShopifyID
 * @property string Price
 * @property string SKU
 * @property int Sort
 * @property string Option1
 * @property string Option2
 * @property string Option3
 * @property boolean Tax
 * @property string Barcode
 * @property int Inventory
 * @property int Grams
 * @property float Weight
 * @property string WeightUnit
 * @property int InventoryItemID
 * @property boolean RequiresShipping
 *
 * @property int ProductID
 * @method   Product Product()
 * @property int ImageID
 * @method   Image Image()
 */
class ProductVariant extends DataObject
{
    private static $table_name = 'ShopifyProductVariant';

    private static $db = [
        'Title' => 'Varchar',
        'ShopifyID' => 'Varchar',
        'Price' => 'Currency',
        'CompareAt' => 'Currency',
        'SKU' => 'Varchar',
        'Sort' => 'Int',
        'Option1' => 'Varchar',
        'Option2' => 'Varchar',
        'Option3' => 'Varchar',
        'Tax' => 'Boolean',
        'Barcode' => 'Varchar',
        'Inventory' => 'Int',
        'Grams' => 'Int',
        'Weight' => 'Decimal',
        'WeightUnit' => 'Varchar',
        'InventoryItemID' => 'Varchar',
        'RequiresShipping' => 'Boolean',
        'Location' => 'Int'
    ];

    private static $default_sort = 'Sort';

    private static $data_map = [
        'id'=> 'ShopifyID',
        'title'=> 'Title',
        'price'=> 'Price',
        'compare_at_price' => 'CompareAt',
        'sku'=> 'SKU',
        'position' => 'Sort',
        'option1' => 'Option1',
        'option2' => 'Option2',
        'option3' => 'Option3',
        'created_at' => 'Created',
        'updated_at' => 'LastEdited',
        'taxable' => 'Tax',
        'barcode' => 'Barcode',
        'grams' => 'Grams',
        'inventory_quantity' => 'Inventory',
        'weight' => 'Weight',
        'weight_unit' => 'WeightUnit',
        'inventory_item_id' => 'InventoryItemID',
        'requires_shipping' => 'RequiresShipping'
    ];

    private static $has_one = [
        'Product' => Product::class,
        'Image' => ShopifyImage::class
    ];

    private static $indexes = [
        'ShopifyID' => true
    ];

    private static $summary_fields = [
        'Image.CMSThumbnail' => 'Image',
        'Title',
        'Price',
        'SKU',
        'ShopifyID'
    ];

    private static $searchable_fields = [
        'Title' => ['title' => 'Title'],
        'SKU' => ['title' => 'SKU'],
        'ShopifyID' => ['title' => 'ShopifyID']
    ];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->extend('updateProductVariant', $this);
    }

    /**
     * Creates a new Shopify Variant from the given data
     *
     * @param  $shopifyVariant
     * @return ProductVariant
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($shopifyVariant)
    {
        if (!$variant = self::getByShopifyID($shopifyVariant->id)) {
            $variant = self::create();
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $variant, $shopifyVariant);

        if ($image = ShopifyImage::getByShopifyID($shopifyVariant->image_id)) {
            $variant->ImageID = $image->ID;
        }

        // Fix for inventory not updating
        if ($shopifyVariant->inventory_quantity == 0) {
            $variant->Inventory = 0;
        }

        // Fix for compare_at_price not updating
        if ($shopifyVariant->compare_at_price == '') {
            $variant->CompareAt = 0;
        }

        $variant->write();

        return $variant;
    }

    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }

    public function canView($member = null)
    {
        return $this->Product()->canView($member);
    }

    public function canEdit($member = null)
    {
        return $this->Product()->canEdit($member);
    }

    public function canDelete($member = null)
    {
        return $this->Product()->canDelete($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->Product()->canCreate($member, $context);
    }
}
