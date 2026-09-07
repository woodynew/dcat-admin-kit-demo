<?php

namespace App\Providers;

use Mosiboom\DcatIframeTab\IframeTabProvider;

/** Use the real package shell; opt into its compact child layout per request. */
class DemoIframeServiceProvider extends IframeTabProvider
{
    public function register()
    {
        if (! $this->app->runningInConsole()
            && (request()->query('iframe') === '1' || request()->header('Sec-Fetch-Dest') === 'iframe')) {
            parent::register();
        }
    }
}
