<?php
namespace Injector;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use JShrink\Minifier;

class Injector
{
    protected $sourceDir = null;
    protected $webDir = null;
    protected $deployDir = null;
    protected $defs = array();
    protected $debug;
    protected $scripts = array();

    private $moduleList = array();

    /**
     * Inicializa o loader de scripts.
     *
     * @param $sourceDir
     * @param $webDir
     */
    public function __construct($sourceDir, $webDir, $deployDir, $defs, $debug = false)
    {
        $this->sourceDir = $sourceDir;
        $this->webDir = $webDir;
        $this->deployDir = $deployDir;
        $this->defs = $defs;
        $this->debug = $debug;
    }

    /**
     * Carrega um módulo de scripts.
     *
     * @param string  $module
     *
     * @return string
     *
     * @throws \Exception
     */
    public function inject($module)
    {
        $paramKey = 'inject.'.$module;
        $this->moduleList = array();

        if (!isset($this->defs[$paramKey])) {
            throw new \Exception('O módulo '.$module.' não foi definido no JsLoader!');
        } else {
            return $this->generateJs($paramKey);
        }

    }

    /**
     * Gera os scripts dos módulos de acordo com a definição de configuração.
     *
     * @param string  $paramKey
     *
     * @return string
     */
    protected function generateJs($paramKey)
    {
        $key = explode('.',$paramKey);
        $buildFileName = end($key).".build.js";
        $buildFileFullname = $this->deployDir."/".$buildFileName;

        if (!$this->debug && file_exists($buildFileFullname)) {
            printf("<script type='text/javascript' src='%s'></script>\n","./".$this->deployDir."/".$buildFileName);
        } else {

            $scripts = $this->buildScriptList($paramKey);

            $include = '';

            foreach ($scripts as $module => $list) {

                foreach ($list as $script) {

                    if ($this->debug) {
                        $include.= sprintf("<script type='text/javascript' src='%s'></script>\n", $script);
                    } else {
                        @$include.= "\n".file_get_contents($this->webDir."/".$script)."\n";
                    }
                }
            }

            if ($this->debug) {
                echo $include;
            } else {
                file_put_contents($this->deployDir."/".$buildFileName,Minifier::minify($include));

                printf("<script type='text/javascript' src='%s/%s'></script>\n",$this->deployDir,$buildFileName);

            }


        }

    }

    /**
     * Cria a lista de scripts a serem inseridos no carregamento.
     *
     * @param string $module
     *
     * @return array
     */
    protected function buildScriptList($module)
    {
        $path = $this->defs[$module];

        $list = $this->getScriptList($module, $path);
        $list = array('module' => $list);

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
    private function getScriptList($module, $path)
    {
        $finder = new Finder();

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

            $files = $finder->files()->in($dir)->name('*.js')->depth("== 0");

            foreach ($files as $script) {
                $list[] = $dir.$script->getRelativePath()."/".$script->getFilename();
            }
        }


        return $list;
    }
}