<?php

namespace Merkeleon\ElasticReader\Providers;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Merkeleon\ElasticReader\Elastic\Elastic;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ElasticReaderServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            dirname(__DIR__) . '/config/elastic_search.php' => config_path('elastic_search.php'),
        ]);
    }

    public function register()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/elastic_search.php', 'elastic_search'
        );

        $this->app->register(EventServiceProvider::class);

        $this->app->bind(Elastic::class, function ($app) {
            $clientBuilder = ClientBuilder::create()
                    ->setHosts(config('elastic_search.hosts'));
            
            if (config('elastic_search.logger_enabled')) {
                $logger = new Logger('log');
                $handler = new StreamHandler(config('elastic_search.logPath'), Logger::WARNING);
                $logger->pushHandler($handler);
                $clientBuilder->setLogger($logger);
            }
            
            return new Elastic($clientBuilder->build());
        });
    }
}
