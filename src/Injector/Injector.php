<?php

namespace Injector;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use JShrink\Minifier;
use Less_Parser;

class Injector
{
    protected $sourceDir = null;
    protected $webDir = null;
    protected $deployDir = null;
    protected $defs = array();
    protected $compile;
    protected $minify;
    protected $scripts = array();
    private $moduleList = array();

    /**
     * Injector constructor.
     *
     * @param string $webDir    Diretório web da aplicação
     * @param string $deployDir Diretório onde serão gerados os arquivos de deploy
     * @param array  $sources   Chave das fontes de extração de assets
     * @param bool   $build     Se deverá ser gerado um único arquivo de build para cada fonte de extração
     * @param bool   $minify    Se o arquivo de build deverá ser minificado.
     */
    public function __construct($webDir, $deployDir, $sources, $build = false, $minify = false)
    {
        $this->webDir = $webDir;
        $this->deployDir = $deployDir;
        $this->defs = $sources;
        $this->compile = $build;
        $this->minify = $minify;
        $this->checkDeployDir();
    }

    /**
     * Carrega um módulo de scripts.
     *
     * @param string $module
     * @param string $type
     * @param bool   $compile
     *
     * @return string
     *
     * @throws \Exception
     */
    public function inject($module, $type = 'js', $compile = false)
    {
        $this->moduleList = array();
        if ($this->compile) {
            $compile = true;
        }
        if (!isset($this->defs['inject.'.$module])) {
            throw new \Exception(
                sprintf(
                    'O módulo %s não foi definido nas configurações. Parâmetro "inject.%s" não localizado!', $module
                )
            );
        } else {
            return $this->injectResource($module, $type, $compile);
        }
    }

    /**
     * Gera os scripts dos módulos de acordo com a definição de configuração.
     *
     * @param string $module
     * @param string $type
     * @param bool   $compile
     *
     * @return string
     */
    protected function injectResource($module, $type, $compile)
    {
        $ext = $this->getResourceExtension($type);
        $buildFileName = $module.'.build.'.$ext;
        $buildFileFullname = $this->deployDir.'/'.$buildFileName;
        $path = str_replace($this->webDir, '', $this->deployDir).'/';
        // se está em estado de compilação e o arquivo compilado existe
        if ($compile && file_exists($buildFileFullname)) {
            if ($type == 'less') {
                $type = 'css';
            }
            echo $this->createIncludeTag($path.$buildFileName, $type);
        } else {
            // recupera a lista de recursos
            $scripts = $this->buildResourceList($module, $type);
            // concatena os recursos para inclusão no template
            $resource = $this->concatResources($scripts, $type, $compile);
            if ($compile) { // se é para compilar
                // salva  o arquivo da compilação do recurso
                if ($this->minify && $type == 'js') {
                    file_put_contents($buildFileFullname, Minifier::minify($resource, array('flaggedComments' => false)));
                } else {
                    file_put_contents($buildFileFullname, $resource);
                }
                // escreve o include da compilação
                echo $this->createIncludeTag($path.$buildFileName, $type);
            } else {
                // escreve as tags de inclusão dos recursos não compilados
                echo $resource;
            }
        }
    }

    /**
     * Concatena os recursos para criação do script de build ou tags de inclusão.
     *
     * @param array  $resources
     * @param string $type
     * @param bool   $compile
     *
     * @return string
     */
    private function concatResources($resources, $type, $compile)
    {
        $concat = '';
        foreach ($resources as $script) {
            if ($compile) {
                if ($type == 'less') {
                    @$concat .= "\n".$this->parseLess($this->webDir.'/'.$script)."\n";
                } else {
                    @$concat .= "\n".file_get_contents($this->webDir.'/'.$script)."\n";
                }
            } else {
                $concat .= $this->createIncludeTag($script, $type);
            }
        }

        return $concat;
    }

    /**
     * Cria a tag de inclusão do recurso de acordo com o seu tipo.
     *
     * @param string $file
     * @param string $type
     *
     * @return string
     */
    protected function createIncludeTag($file, $type)
    {
        switch ($type) {
            case 'js':
                return sprintf("<script type='text/javascript' src='%s'></script>\n", $file);
            case 'css':
                return sprintf("<link rel='stylesheet' href='%s'>\n", $file);
            case 'less':
                return sprintf("<style>\n%s</style>", $this->parseLess($file));
        }
    }

    /**
     * Faz o parse do arquivo less.
     *
     * @param string $file
     *
     * @return string Conteúdo parseado
     */
    protected function parseLess($file)
    {
        $parser = new Less_Parser();
        $parser->parseFile($file);

        return $parser->getCss();
    }

    /**
     * Cria a lista de scripts a serem inseridos no carregamento.
     *
     * @param string $module
     * @param string $type
     *
     * @return array
     */
    protected function buildResourceList($module, $type)
    {
        $path = $this->defs['inject.'.$module];
        $list = $this->getResourceList($module, $path, $type);

        return $list;
    }

    /**
     * Recupera a lista de scripts associado a um determinado módulo.
     *
     * @param string $module
     * @param string $path
     *
     * @return array
     */
    private function getResourceList($module, $path, $type)
    {
        $finder = new Finder();
        if (is_file($path)) {
            return [$path];
        }
        $dirs = $finder->directories()->in($path)->sortByType();
        $directories = array(
            $path,
        );
        foreach ($dirs as $dir) {
            $directory = $path.'/'.$dir->getRelativePath().'/'.$dir->getFilename();
            $directory = str_replace('//', '/', $directory);
            $directories[] = $directory;
        }
        $list = array();
        foreach ($directories as $dir) {
            $finder = new Finder();
            $files = $finder->files()->in($dir)->name('*.'.$type)->depth('== 0')->sortByName();
            foreach ($files as $script) {
                $list[] = $dir.$script->getRelativePath().'/'.$script->getFilename();
            }
        }

        return $list;
    }

    /**
     * Retorna a extensão de arquivo para compilação de um tipo de recurso.
     *
     * @param string $type
     *
     * @return string
     */
    private function getResourceExtension($type)
    {
        $ext = $type;
        if ($ext == 'less') {
            $ext = 'css';
        }

        return $ext;
    }

    /**
     * Verifica se o diretório de deploy existe, e, caso não exista, força sua criação.
     */
    private function checkDeployDir()
    {
        $fs = new Filesystem();
        if (!$fs->exists($this->deployDir)) {
            $fs->mkdir($this->deployDir);
        }
    }
}
