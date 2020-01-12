<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;

$app = new Silex\Application(array(
    "injector.directory.web" => __DIR__,
    "injector.directory.src" => __DIR__."/js",
    "injector.directory.deploy" => __DIR__."/deploy",
    "injector.compile" => false,
    "injector.minify" => false,
    "debug" => true
));

$app->register(new \Injector\Provider\InjectorServiceProvider(), [
    'inject.modulo' => 'js/modulo',
    'inject.estilo' => 'css'
]);

$app->get('/test/inject', function() use ($app) {
    echo "<pre>";
    $app['injector']->inject('modulo', 'js');
    return '';
});

$app->get('/test/deploy', function() use ($app){
    $app['injector']->inject('modulo','js', true, true, 2);
    return '';
});

$app->get('/test/inject/css', function() use ($app){
    echo "<pre>";
    $app['injector']->inject('estilo','css', true);
    die();
});

$app->run();