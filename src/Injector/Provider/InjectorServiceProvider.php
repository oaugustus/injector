<?php
namespace Injector\Provider;


use Injector\Injector;
use Silex\Application;
use Silex\ServiceProviderInterface;

class InjectorServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['injector'] = $app->share(function() use ($app){
            $keys = $app->keys();
            $defs = array();

            foreach ($keys as $key){
                if (strpos($key,'inject.') !== false){
                    $defs[$key] = $app[$key];
                }
            }

            if (!$app['src_dir']) {
                throw new \Exception('Não foi definido o diretório de scripts da aplicação ($app["src_dir"])!');
            }

            if (!$app['web_dir']) {
                throw new \Exception('Não foi definido o diretório web da aplicação ($app["web_dir"])!');
            }

            if (!$app['deploy_dir']) {
                throw new \Exception('Não foi definido o caminho da geração dos builds da aplicação ($app["deploy_dir"])!');
            }

            return new Injector($app['src_dir'], $app['web_dir'], $app['deploy_dir'], $defs, $app['injector.compile'], $app['injector.minify']);
        });
    }

    public function boot(Application $app)
    {

    }

}