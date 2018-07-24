<?php

namespace Injector\Provider;

use Injector\Injector;
use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Class InjectorServiceProvider.
 *
 * Provedor de serviços do injetor de assets na front-view.
 */
class InjectorServiceProvider implements ServiceProviderInterface
{
    /**
     * Executado no ato de registro do provedor de serviços.
     *
     * @param Application $app
     */
    public function register(Application $app)
    {
        $app['injector'] = $app->share(function () use ($app) {
            $keys = $app->keys();
            $defs = array();

            foreach ($keys as $key) {
                if (strpos($key, 'inject.') !== false) {
                    $defs[$key] = $app[$key];
                }
            }

            if (!$app['web_dir']) {
                throw new \Exception(
                    'Não foi definido o diretório web da aplicação ($app["web_dir"])!'
                );
            }

            if (!$app['deploy_dir']) {
                throw new \Exception(
                    'Não foi definido o caminho da geração dos builds da aplicação ($app["deploy_dir"])!'
                );
            }

            return new Injector(
                $app['web_dir'],
                $app['deploy_dir'],
                $defs,
                $app['injector.compile'],
                $app['injector.minify']
            );
        });
    }

    /**
     * Função de boot do provedor.
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
    }
}
