<?php

namespace Swordfox\Shopify\Model;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\TagField\TagField;
use SilverStripe\ORM\FieldType\DBIndexable;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\FieldType\DBCurrency;
use Swordfox\Shopify\Task\Import;
use Swordfox\Shopify\Client;

/**
 * Class ShippingProvince
 *
 * @author     Graham McLellan
 * @package    Swordfox\Shopify
 * @subpackage Model
 *
 * @mixin Versioned
 *
 * @property string Name
 * @property string Code
 * @property double Tax
 * @property string TaxName
 * @property string ShopifyShippingZoneID
 * @property string ShopifyID
 */
class ShippingProvince extends DataObject
{
    private static $table_name = 'ShopifyShippingProvince';

    private static $default_sort = 'Name';

    private static $create_table_options = [
        MySQLSchemaManager::ID => 'ENGINE=MyISAM',
    ];

    private static $db = [
        'Name' => 'Varchar',
        'Code' => 'Varchar',
        'Tax' => 'Double',
        'TaxName' => 'Varchar',
        'ShopifyShippingZoneID' => 'Varchar',
        'ShopifyID' => 'Varchar',
    ];

    private static $data_map = [
        'id' => 'ShopifyID',
        'name' => 'Name',
        'code' => 'Code',
        'tax' => 'Tax',
        'tax_name' => 'TaxName',
        'shipping_zone_id' => 'ShopifyShippingZoneID',
    ];

    private static $has_one = [
        'ShippingCountry' => ShippingCountry::class,
    ];

    private static $summary_fields = [
        'Name',
    ];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->extend('updateShippingProvince', $this);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $dataFields = $fields->dataFields();

        $dontmakereadonly = [];

        foreach ($dataFields as $dataField) {
            if (!in_array($dataField->Name, $dontmakereadonly)) {
                $fields->makeFieldReadonly($dataField->Name);
            }
        }

        return $fields;
    }

    /**
     * Creates a new Shopify Shipping Zone from the given data
     * but does not publish it
     *
     * @param  $data
     * @return ShippingProvince
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($data)
    {
        if (!$ShippingProvince = self::getByShopifyID($data->id)) {
            $ShippingProvince = self::create();
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $ShippingProvince, $data);

        $ShippingProvince->write();
        return $ShippingProvince;
    }

    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }
}
