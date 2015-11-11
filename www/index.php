<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component as sFc;
use Silex\Provider as silP;

// Boot my app
$app = new Silex\Application();
// Detect environment (default: prod)
$app['env'] = 'prod';
if (array_key_exists('env', $_ENV)) {
    $app['env'] = $_ENV['env'];
}
// Register all services
$app->register(new silP\SessionServiceProvider());
$app->register(new silP\TranslationServiceProvider(), ['translator.domains' => []]);
$app->register(new silP\UrlGeneratorServiceProvider());
// Everything about forms
$app->register(new silP\FormServiceProvider());
$app->register(new silP\ValidatorServiceProvider());
// Twig Templates
$app->register(new silP\TwigServiceProvider(), ['twig.path' => __DIR__.'/../src/Views']);
// Routes are in a config
$app['routes'] = $app->extend('routes', function (sFc\Routing\RouteCollection $routes, Silex\Application $app) {
    $loader = new sFc\Routing\Loader\YamlFileLoader(new sFc\Config\FileLocator(__DIR__ . '/../config'));
    $collection = $loader->load('routes.yml');
    $routes->addCollection($collection);

    return $routes;
});

// Run
if ($app['env'] == 'dev') {
    $app['debug'] = true;
} elseif ($app['env'] == 'test') {
    return $app;
}
$app->run();
