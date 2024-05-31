<?php

namespace Swordfox\Shopify\SiteConfig;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataExtension;

use Swordfox\Shopify\Model\ShippingCountry;

/**
 * Class ShopifySiteConfig
 *
 * @author  Graham McLellan
 * @package Swordfox\Shopify\SiteConfig
 */

class ShopifySiteConfig extends DataExtension
{
    private static $db = [
        'ShopifyCurrency' => 'Varchar(20)'
    ];

    private static $has_one = [
        'ShopifyShippingCountry' => ShippingCountry::class,
    ];

    public function populateDefaults()
    {
        $this->owner->ShopifyCurrency = 'NZD';
        parent::populateDefaults();
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab('Root.Shopify', [
            TextField::create('ShopifyCurrency', 'Default Currency')
                ->setDescription('3 letter currency code e.g. NZD, AUD or USD etc'),
            DropdownField::create('ShopifyShippingCountryID', 'Default Shipping Country', ShippingCountry::get()->sort('Name')->map('ID', 'Name')->toArray())
                ->setEmptyString(' - Select -')
        ]);
    }
}
