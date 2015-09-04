<?php

require_once __DIR__.'/../vendor/autoload.php';

use Edyan\MysqlDiff\Controller\AppController;
use Symfony\Component\HttpFoundation\Request;

// Boot my app
$app = new Silex\Application();
$app['debug'] = false;

// Register all services
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__.'/../src/Edyan/MysqlDiff/Views',
]);
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
// Everything about forms
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), [
    'translator.domains' => [],
]);

// Routes
$app->get('/options/servers', function () use ($app) {
    $controller = new AppController;
    return $controller->getOptionsServers($app);
});

// Catch the post
$app->post('/options/servers', function (Request $request) use ($app) {
    $controller = new AppController;
    return $controller->postOptionsServers($app, $request);
});

// Catch the post
$app->post('/options/databases', function (Request $request) use ($app) {
    $controller = new AppController;
    return $controller->postOptionsDatabases($app, $request);
});

// Catch the get
// The bind is to define the root for URL Generator
$app->get('/options/what-to-compare', function (Request $request) use ($app) {
    $controller = new AppController;
    return $controller->getOptionsWhatToCompare($app, $request);
})->bind('what-to-compare');

$app->post('/options/what-to-compare', function (Request $request) use ($app) {
    $controller = new AppController;
    return $controller->postOptionsWhatToCompare($app, $request);
});

// Get the results
$app->get('/results', function (Request $request) use ($app) {
    $controller = new AppController;
    return $controller->getResults($app, $request);
})->bind('results');

// Run
$app->run();
