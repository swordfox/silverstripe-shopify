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
 * Class ShippingZone
 *
 * @author     Graham McLellan
 * @package    Swordfox\Shopify
 * @subpackage Model
 *
 * @mixin Versioned
 *
 * @property string Name
 * @property string ShopifyID
 *
 * @method HasManyList ShippingCountries()
 * @method HasManyList ShippingRates()
 */
class ShippingZone extends DataObject
{
    private static $table_name = 'ShopifyShippingZone';

    private static $default_sort = 'Name';

    private static $create_table_options = [
        MySQLSchemaManager::ID => 'ENGINE=MyISAM',
    ];

    private static $db = [
        'Name' => 'Varchar',
        'Exclude' => 'Boolean',
        'ShopifyID' => 'Varchar',
    ];

    private static $data_map = [
        'id' => 'ShopifyID',
        'name' => 'Name',
    ];

    private static $has_many = [
        'ShippingCountries' => ShippingCountry::class,
        'ShippingRates' => ShippingRate::class,
    ];

    private static $cascade_deletes = [
        'ShippingCountries',
        'ShippingRates',
    ];

    private static $owns = [
        'ShippingCountries',
        'ShippingRates',
    ];

    private static $summary_fields = [
        'Name',
    ];

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->extend('updateShippingZone', $this);
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
            'Root.ShippingCountries',
            [
                GridField::create('ShippingCountries', 'Countries', $this->ShippingCountries(), GridFieldConfig_RecordViewer::create())
            ]
        );

        $fields->addFieldsToTab(
            'Root.ShippingRates',
            [
                GridField::create('ShippingRates', 'Rates', $this->ShippingRates(), GridFieldConfig_RecordViewer::create())
            ]
        );

        return $fields;
    }

    /**
     * Creates a new Shopify Shipping Zone from the given data
     * but does not publish it
     *
     * @param  $shopifyShippingZone
     * @return ShippingZone
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($shopifyShippingZone)
    {
        if (!$shippingZone = self::getByShopifyID($shopifyShippingZone->id)) {
            $shippingZone = self::create();
        }

        // Create the countries
        $currentcountries = $shippingZone->ShippingCountries();
        $allcountries = [];

        if (!empty($shopifyShippingZone->countries)) {
            foreach ($shopifyShippingZone->countries as $shopifyShippingCountry) {
                array_push($allcountries, $shopifyShippingCountry->id);

                if (!$shippingCountry = self::getByShopifyID($shopifyShippingCountry->id)) {

                    if ($shippingCountry = $shippingZone->importObject(ShippingCountry::class, $shopifyShippingCountry)) {
                        $shippingZone->ShippingCountries()->add($shippingCountry);
                    }
                }
            }
        }

        // Remove any countries that have been deleted.
        foreach ($currentcountries as $currentcountry) {
            if (!in_array($currentcountry->ShopifyID, $allcountries)) {
                $shippingZone->ShippingCountries()->remove($currentcountry);
            }
        }

        // Create the rates
        $currentrates = $shippingZone->ShippingRates();
        $allrates = [];

        if (!empty($shopifyShippingZone->weight_based_shipping_rates)) {
            foreach ($shopifyShippingZone->weight_based_shipping_rates as $shopifyShippingRate) {
                $shopifyShippingRate->type = 'WeightBased';
                array_push($allrates, $shopifyShippingRate->id);

                if (!$shippingRate = self::getByShopifyID($shopifyShippingRate->id)) {
                    if ($shippingRate = $shippingZone->importObject(ShippingRate::class, $shopifyShippingRate)) {
                        $shippingZone->ShippingRates()->add($shippingRate);
                    }
                }
            }
        }

        if (!empty($shopifyShippingZone->price_based_shipping_rates)) {
            foreach ($shopifyShippingZone->price_based_shipping_rates as $shopifyShippingRate) {
                $shopifyShippingRate->type = 'PriceBased';
                array_push($allrates, $shopifyShippingRate->id);

                if (!$shippingRate = self::getByShopifyID($shopifyShippingRate->id)) {
                    if ($shippingRate = $shippingZone->importObject(ShippingRate::class, $shopifyShippingRate)) {
                        $shippingZone->ShippingRates()->add($shippingRate);
                    }
                }
            }
        }

        // Remove any rates that have been deleted.
        foreach ($currentrates as $currentrate) {
            if (!in_array($currentrate->ShopifyID, $allrates)) {
                $shippingZone->ShippingRates()->remove($currentrate);
            }
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $shippingZone, $shopifyShippingZone);

        $shippingZone->write();
        return $shippingZone;
    }

    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }
}
