<?php

namespace RethinkIT\RedisCart;

use RethinkIT\RedisCart\RedisCart;
use Illuminate\Support\ServiceProvider;

class RedisCartServiceProvider extends ServiceProvider

{
    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rediscart.php', 'rediscart'
        );

        $this->publishes([
            __DIR__.'/../config/rediscart.php' => config_path('rediscart.php')
        ], 'rediscart');
    }

    public function register()
    {
        $this->app->bind('rediscart', 'RethinkIT\RedisCart\RedisCart');

        $this->app->singleton(RedisCart::class, function(){
            return new RedisCart();
        });
    }
}