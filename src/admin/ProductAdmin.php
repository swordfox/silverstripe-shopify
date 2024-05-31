<?php

namespace Swordfox\Shopify\Admin;

use SilverStripe\Admin\ModelAdmin;
use Swordfox\Shopify\Model\Product;
use Swordfox\Shopify\Model\Collection;
use Swordfox\Shopify\Model\ShippingZone;
use Swordfox\Shopify\Model\ShippingCountry;

use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class ProductAdmin
 * @package Swordfox\Shopify\Admin
 */
class ProductAdmin extends ModelAdmin
{
    private static $managed_models = [
        Collection::class,
        Product::class,
        ShippingZone::class,
        ShippingCountry::class,
    ];

    private static $url_segment = 'shopify';

    private static $menu_title = 'Shopify';

    private static $menu_icon = 'vendor/swordfox/silverstripe-shopify/images/shopify_glyph.svg';

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // Get grid field
        $gridFieldName = $this->sanitiseClassName($this->modelClass);
        $gridField = $form->Fields()->fieldByName($gridFieldName);

        // Get the grid field config
        $config = $gridField->getConfig();
        $config->removeComponentsByType(GridFieldDeleteAction::class);

        // Remove export and print buttons
        $config->removeComponentsByType(GridFieldAddNewButton::class);
        $config->removeComponentsByType(GridFieldImportButton::class);
        $config->removeComponentsByType(GridFieldPrintButton::class);

        $columns = $config->getComponentByType(GridFieldDataColumns::class);

        if ($this->modelClass == 'Swordfox\Shopify\Model\Collection') {
            $config->addComponent(new GridFieldOrderableRows('SortOrder'));
        }

        $this->extend('updateEditForm', $form);

        return $form;
    }
}
