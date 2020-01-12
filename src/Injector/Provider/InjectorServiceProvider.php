<?php
namespace Injector\Provider;

use Injector\Injector;
use Silex\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Provedor de serviços do módulo Injector.
 *
 * @package Injector\Provider
 */
class InjectorServiceProvider implements ServiceProviderInterface
{
    /**
     * Registra os serviços do módulo.
     *
     * @param Container $app
     */
    public function register(Container $app)
    {
        $app['injector'] = function () use ($app) {
            $keys = $app->keys();
            $defs = [];

            foreach ($keys as $key) {
                if (strpos($key, 'inject.') !== false) {
                    $defs[$key] = $app[$key];
                }
            }

            if (!$app['injector.directory.src']) {
                throw new \Exception('Não foi definido o diretório de scripts da aplicação ($app["injector.directory.src"])!');
            }

            if (!$app['injector.directory.web']) {
                throw new \Exception('Não foi definido o diretório web da aplicação ($app["injector.directory.web"])!');
            }

            if (!$app['injector.directory.deploy']) {
                throw new \Exception('Não foi definido o caminho da geração dos builds da aplicação ($app["injector.directory.deploy"])!');
            }

            return new Injector(
                $app['injector.directory.src'],
                $app['injector.directory.web'],
                $app['injector.directory.deploy'],
                $defs,
                $app['injector.compile'],
                $app['injector.minify']
            );
        };
    }
}
