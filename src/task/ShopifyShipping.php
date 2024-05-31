<?php

namespace Swordfox\Shopify\Task;

ini_set('max_execution_time', 300);

use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;

use Swordfox\Shopify\Client;

use SilverStripe\Dev\Debug;
use SilverStripe\Dev\Backtrace;

/**
 * Class Import
 *
 * @author Graham McLellan
 */
class ShopifyShipping extends BuildTask
{
    protected $title = 'Shopify shipping zones/rates/countries';

    protected $description = 'Shopify shipping zones/rates/countries';

    protected $enabled = true;

    public function run($request)
    {
        if (!Director::is_cli()) {
            echo "<pre>";
        }

        try {
            $client = new Client();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        $this->updateShipping($client);

        if (!Director::is_cli()) {
            echo "</pre>";
        }
        exit('Done');
    }
}
