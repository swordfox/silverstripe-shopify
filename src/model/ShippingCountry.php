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
 * Class ShippingCountry
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
class ShippingCountry extends DataObject
{
    private static $table_name = 'ShopifyShippingCountry';

    private static $default_sort = 'Name';

    private static $create_table_options = [
        MySQLSchemaManager::ID => 'ENGINE=MyISAM',
    ];

    private static $db = [
        'Name' => 'Varchar',
        'Exclude' => 'Boolean',
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
        'ShippingZone' => ShippingZone::class,
    ];

    private static $has_many = [
        'ShippingProvinces' => ShippingProvince::class,
    ];

    private static $cascade_deletes = [
        'ShippingProvinces',
    ];

    private static $owns = [
        'ShippingProvinces',
    ];

    private static $summary_fields = [
        'Name',
    ];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->extend('updateShippingCountry', $this);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $dataFields = $fields->dataFields();

        $dontmakereadonly = ['Exclude'];

        foreach ($dataFields as $dataField) {
            if (!in_array($dataField->Name, $dontmakereadonly)) {
                $fields->makeFieldReadonly($dataField->Name);
            }
        }

        $fields->addFieldsToTab(
            'Root.ShippingProvinces',
            [
                GridField::create('ShippingProvinces', 'Provinces', $this->ShippingProvinces(), GridFieldConfig_RecordViewer::create())
            ]
        );

        return $fields;
    }

    /**
     * Creates a new Shopify Shipping Zone from the given data
     * but does not publish it
     *
     * @param  $data
     * @return ShippingCountry
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($data)
    {
        if (!$shippingCountry = self::getByShopifyID($data->id)) {
            $shippingCountry = self::create();
        }

        //print_r($data);

        // Create the provinces
        $currentprovinces = $shippingCountry->ShippingProvinces();
        $allprovinces = [];

        if (!empty($data->provinces)) {
            foreach ($data->provinces as $shopifyShippingProvince) {
                array_push($allprovinces, $shopifyShippingProvince->id);

                if (!$shippingProvince = self::getByShopifyID($shopifyShippingProvince->id)) {

                    if ($shippingProvince = $shippingCountry->importObject(ShippingProvince::class, $shopifyShippingProvince)) {
                        $shippingCountry->ShippingProvinces()->add($shippingProvince);
                    }
                }
            }
        }

        // Remove any provinces that have been deleted.
        foreach ($currentprovinces as $currentprovince) {
            if (!in_array($currentprovince->ShopifyID, $allprovinces)) {
                $shippingCountry->ShippingProvinces()->remove($currentprovince);
            }
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $shippingCountry, $data);

        $shippingCountry->write();
        return $shippingCountry;
    }

    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }
}
