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
class InventoryLocation extends BuildTask
{
    protected $title = 'Shopify inventory location';

    protected $description = 'Update inventory location';

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

        $locations = $client->locations();

        if (($locations = $locations->getBody()->getContents()) && $locations = Convert::json2obj($locations)) {
            foreach ($locations->locations as $location) {
                $this->updateInventoryLocation($client, $location->id);
            }
        }

        if (!Director::is_cli()) {
            echo "</pre>";
        }
        exit('Done');
    }
}
