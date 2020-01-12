<?php
namespace Injector;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use JShrink\Minifier;
use Less_Parser;

class Injector
{
    /**
     * Diretório principal da aplicação.
     *
     * @deprecated
     * @var String
     */
    protected $sourceDir = null;

    /**
     * Diretório público da aplicação.
     *
     * @var String
     */
    protected $webDir = null;

    /**
     * Diretório onde serão gerados os arquivos de deploy dos módulos injetados.
     *
     * @var String
     */
    protected $deployDir = null;

    /**
     * Definição dos módulos que devem ser injetados.
     *
     * Os módulos devem ser configurados no container de dependência com o prefixo 'inject.'.
     * Exemplo um módulo javascript chamado security, seria configurado no container de dependências
     * como 'inject.security'. O valor desse parâmetro no container de dependências será o caminho
     * dos scripts do módulo a partir do diretório web.
     *
     * @var array
     */
    protected $defs = [];

    /**
     * Se os scripts devem ser concatenados antes de serem injetados.
     *
     * @var bool
     */
    protected $concat;

    /**
     * Se os scripts devem ser minificados antes de serem injetados.
     *
     * @var bool
     */
    protected $minify;

    /**
     * Relação de todos os scripts (css ou js) a serem injetados.
     *
     * @var array
     */
    protected $scripts = [];

    /**
     * Lista dos módulos a serem injetados.
     *
     * @var array
     */
    private $moduleList = [];

    /**
     * Injector constructor.
     *
     * @param $sourceDir
     * @param $webDir
     * @param $deployDir
     * @param $defs
     * @param bool $concat
     * @param bool $minify
     */
    public function __construct($sourceDir, $webDir, $deployDir, $defs, $concat = false, $minify = false)
    {
        $this->sourceDir = $sourceDir;
        $this->webDir = $webDir;
        $this->deployDir = $deployDir;
        $this->defs = $defs;
        $this->concat = $concat;
        $this->minify = $minify;

        $this->checkDeployDir();
    }

    /**
     * Carrega um módulo de scripts.
     *
     * @param string  $module  Módulo que será injetado (diretório do módulo)
     * @param string  $type    Tipo de módulo a ser injetado (css ou js)
     * @param boolean $concat Se o módulo a ser injetado será
     * @param boolean $minify
     * @param integer $version
     *
     * @return string
     *
     * @throws \Exception
     */
    public function inject($module, $type = 'js', $concat = false, $minify = false, $version = 1)
    {
        $this->moduleList = [];

        // se a configuração de concatenação foi definida globalmente
        if ($this->concat === true) {
            $concat = true; // força a concatenação do módulo atual
        }

        // se a configuração de minificação foi definida globalmente
        if ($this->minify === true) {
            $minify = true; // força a minificação do módulo atual
        }

        // se o módulo a ser injetado não foi definido no container de dependências
        if (!isset($this->defs['inject.'.$module])) {
            // lança exceção
            $error = 'O módulo "%s" não foi definido nas configurações. O parâmetro "inject.%s" não foi localizado no 
            container de dependências';

            throw new \Exception(sprintf($error, $module, $module));
        } else {
            return $this->injectResource($module, $type, $concat, $minify, $version);
        }

    }

    /**
     * Gera os scripts dos módulos de acordo com a definição de configuração.
     *
     * @param string  $module
     * @param string  $type
     * @param boolean $concat
     * @param boolean $minify
     * @param string  $version
     *
     * @return string
     */
    protected function injectResource($module, $type, $concat, $minify, $version)
    {
        $ext = $this->getResourceExtension($type);

        $buildFileName = $module.".build.".$ext;
        $buildFileFullname = $this->deployDir."/".$buildFileName;
        $path = ".".str_replace($this->webDir, '', $this->deployDir)."/";

        // se está em estado de compilação e o arquivo compilado existe
        if ($concat && file_exists($buildFileFullname)) {

            if ($type == 'less') {
                $type = 'css';
            }

            print($this->createIncludeTag($path.$buildFileName, $type, $version));
        } else {
            // recupera a lista de recursos
            $scripts = $this->buildResourceList($module, $type);

            // concatena os recursos para inclusão no template
            $resource = $this->concatResources($scripts, $type, $concat, $version);

            if ($concat) { // se é para compilar

                // salva  o arquivo da compilação do recurso
                if ($minify && $type == 'js') {
                    file_put_contents($buildFileFullname,Minifier::minify($resource, ['flaggedComments' => false]));
                } else {
                    file_put_contents($buildFileFullname, $resource);
                }

                // escreve o include da compilação
                print($this->createIncludeTag($path.$buildFileName, $type, $version));
            } else {
                // escreve as tags de inclusão dos recursos não compilados
                echo $resource;
            }

        }

    }

    /**
     * Concatena os recursos para criação do script de build ou tags de inclusão.
     *
     * @param  array   $resources
     * @param  string  $type
     * @param  boolean $concat
     *
     * @return string
     */
    private function concatResources($resources, $type, $concat, $version)
    {
        $concatenated = '';

        foreach ($resources as $script) {
            if ($concat) {
                if ($type == 'less') {
                    @$concatenated.= "\n".$this->parseLess($this->webDir."/".$script)."\n";
                } else {
                    @$concatenated.= "\n".file_get_contents($this->webDir."/".$script)."\n";
                }
            } else {
                $concatenated.= $this->createIncludeTag($script, $type, $version);
            }
        }

        return $concatenated;
    }

    /**
     * Cria a tag de inclusão do recurso de acordo com o seu tipo.
     *
     * @param string $file
     * @param string $type
     * @param int    $version
     *
     * @return string
     */
    protected function createIncludeTag($file, $type, $version)
    {
        switch ($type) {
            case 'js':
                return sprintf("<script type='text/javascript' src='%s?version=%d'></script>\n",$file, $version);
            case 'css':
                return sprintf("<link rel='stylesheet' href='%s?version=%d'>\n", $file, $version);
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
        $path = $this->defs["inject.".$module];

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

        $directories  = array(
            $path
        );

        foreach ($dirs as $dir) {
            $directory = $path."/".$dir->getRelativePath()."/".$dir->getFilename();
            $directory = str_replace('//','/', $directory);

            $directories[] = $directory;
        }

        $list = array();

        foreach ($directories as $dir) {
            $finder = new Finder();

            $files = $finder->files()->in($dir)->name('*.'.$type)->depth("== 0")->sortByName();

            foreach ($files as $script) {
                $list[] = $dir.$script->getRelativePath()."/".$script->getFilename();
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