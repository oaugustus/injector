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
    protected $compile;
    protected $minify;
    protected $scripts = array();

    private $moduleList = array();

    /**
     * Inicializa o loader de scripts.
     *
     * @param $sourceDir
     * @param $webDir
     */
    public function __construct($sourceDir, $webDir, $deployDir, $defs, $compile = false, $minify = false)
    {
        $this->sourceDir = $sourceDir;
        $this->webDir = $webDir;
        $this->deployDir = $deployDir;
        $this->defs = $defs;
        $this->compile = $compile;
        $this->minify = $minify;
    }

    /**
     * Carrega um módulo de scripts.
     *
     * @param string  $module
     * @param string  $type
     *
     * @return string
     *
     * @throws \Exception
     */
    public function inject($module, $type = 'js', $compile = true)
    {
        $paramKey = 'inject.'.$module;
        $this->moduleList = array();

        if (!isset($this->defs[$paramKey])) {
            throw new \Exception('O módulo '.$module.' não foi definido nas configurações. O parâmetro "inject.'.$module.' não foi localizado!"');
        } else {
            return $this->injectResource($paramKey, $type, $compile);
        }

    }

    /**
     * Gera os scripts dos módulos de acordo com a definição de configuração.
     *
     * @param string  $paramKey
     * @param string  $type
     * @param boolean $compile
     *
     * @return string
     */
    protected function injectResource($paramKey, $type, $compile)
    {
        $key = explode('.',$paramKey);
        $buildFileName = end($key).".build.".$type;
        $buildFileFullname = $this->deployDir."/".$buildFileName;

        if ($this->compile && file_exists($buildFileFullname)) {
            print($this->createIncludeTag("./".$this->deployDir."/".$buildFileName, $type));
        } else {

            $scripts = $this->buildResourceList($paramKey, $type);

            $include = '';

            foreach ($scripts as $module => $list) {

                foreach ($list as $script) {

                    if ($this->compile && $compile) {
                        @$include.= "\n".file_get_contents($this->webDir."/".$script)."\n";
                    } else {
                        $include.= $this->createIncludeTag($script, $type);
                    }
                }
            }

            if ($this->compile && $compile) {
                if ($this->minify) {
                    file_put_contents($this->deployDir."/".$buildFileName,Minifier::minify($include,array('flaggedComments' => false)));
                } else {
                    file_put_contents($this->deployDir."/".$buildFileName, $include);
                }


                print($this->createIncludeTag("./".$this->deployDir."/".$buildFileName, $type));
            } else {
                echo $include;
            }


        }

    }

    /**
     * Cria a tag de inclusão do recurso de acordo com o seu tipo.
     *
     * @param string $file
     * @param strine $type
     *
     * @return string
     */
    protected function createIncludeTag($file, $type)
    {
        switch ($type) {
            case 'js':
                return sprintf("<script type='text/javascript' src='%s'></script>\n",$file);
            case 'css':
                return sprintf("<link rel='stylesheet' href='%s'>\n", $file);
        }
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
        $path = $this->defs[$module];

        $list = $this->getResourceList($module, $path, $type);
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
    private function getResourceList($module, $path, $type)
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

            $files = $finder->files()->in($dir)->name('*.'.$type)->depth("== 0")->sortByName();

            foreach ($files as $script) {
                $list[] = $dir.$script->getRelativePath()."/".$script->getFilename();
            }
        }


        return $list;
    }
}