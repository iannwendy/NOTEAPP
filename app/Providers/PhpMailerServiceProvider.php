<?php

namespace App\Providers;

use App\Mail\Transport\PhpMailerTransportFactory;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Dsn;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PhpMailerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->afterResolving('mail.manager', function ($manager) {
            $this->registerCustomMailTransport($manager);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register the custom mail transport instance.
     *
     * @param  \Illuminate\Mail\MailManager  $manager
     * @return void
     */
    protected function registerCustomMailTransport($manager): void
    {
        $manager->extend('phpmailer', function () {
            $dispatcher = $this->app->bound(EventDispatcherInterface::class) 
                ? $this->app->make(EventDispatcherInterface::class) 
                : null;
            
            $httpClient = $this->app->bound(HttpClientInterface::class) 
                ? $this->app->make(HttpClientInterface::class) 
                : null;
            
            $logger = $this->app->bound(LoggerInterface::class) 
                ? $this->app->make(LoggerInterface::class) 
                : null;
            
            $factory = new PhpMailerTransportFactory($dispatcher, $httpClient, $logger);
            
            $dsnString = $this->app['config']['mail.mailers.phpmailer.dsn'] ?? 
                   'phpmailer+smtp://127.0.0.1:25';
                   
            return $factory->create(Dsn::fromString($dsnString));
        });
    }
} 