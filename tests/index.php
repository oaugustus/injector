<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;

$app = new Silex\Application(array(
    'src_dir' => __DIR__.'/js',
    'web_dir' => __DIR__,
    'deploy_dir' => __DIR__."/deploy",
    "debug" => true
));

$app->register(new \Injector\Provider\InjectorServiceProvider(), array(
    'inject.modulo' => 'js/modulo',
    'inject.estilo' => 'css'
));

$app->get('/test/inject', function() use ($app){
    echo "<pre>";
    $app['injector']->inject('modulo');
});

$app->get('/test/deploy', function() use ($app){
    echo "<pre>";
    $app['debug'] = false;
    $app['injector']->inject('modulo');
});

$app->get('/test/inject/css', function() use ($app){
    echo "<pre>";
    $app['injector']->inject('estilo','css');
    die();
});

$app->run();