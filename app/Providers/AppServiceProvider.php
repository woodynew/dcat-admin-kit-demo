<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->loadJsonTranslationsFrom(resource_path('translations'));
        // Kit's own configuration is the site baseline for every request.
        $features = config('dcat-admin-kit.features', []);
        $request = $this->app['request'];
        if ($request->is('admin/demo/preview', 'admin/demo/preview-form', 'admin/demo/preview-show/*')) {
            // The comparison page flips only the switch under test; the rest of the
            // baseline (grid_assets, locale_switcher) stays on, so a preview never
            // regresses the styling every other page already shows.
            $feature = $request->query('feature');
            if (is_string($feature) && array_key_exists($feature, $features)) {
                $features[$feature] = $request->query('enabled') === '1';
            }
        }
        // All providers register before any provider boots; this precedes Kit's boot().
        config(['dcat-admin-kit.features' => $features]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        view()->composer('admin::partials.navbar-user-panel', function ($view) {
            $user = $view->getData()['user'] ?? null;
            if ($user && $user->username === 'demo' && $user->name === '演示访客') {
                // Localize only the demo's display label, never the persisted user.
                $displayUser = clone $user;
                $displayUser->name = __('演示访客');
                $view->with('user', $displayUser);
            }
        });
        RateLimiter::for('demo-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
