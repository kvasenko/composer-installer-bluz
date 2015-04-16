<?php
/**
 * @author Pavel Machekhin <pavel.machekhin@gmail.com>
 * @created 2015-03-24 12:39
 */

namespace Bashmach\Composer\Installers;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;
use Composer\Script\CommandEvent;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class BluzModuleInstallerPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $installer;
    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        define('DS', DIRECTORY_SEPARATOR);
        $this->installer = new BluzModuleInstaller($io, $composer);

        $composer->getInstallationmanager()->addInstaller($this->installer);
    }

    public static function getSubscribedEvents() {
        $result = array(
            ScriptEvents::POST_INSTALL_CMD => array(
                array( 'onPostInstallCmd', 0 )
            ),
            ScriptEvents::POST_UPDATE_CMD  => array(
                array( 'onPostUpdateCmd', 0 )
            ),
        );
        return $result;
    }

    public function onPostInstallCmd(CommandEvent $event)
    {
        $this->moveFolders();
    }

    public function onPostUpdateCmd(CommandEvent $event)
    {
        $this->moveFolders();
    }

    public function moveFolders()
    {
        $fs = new Filesystem();

        $settings = $this->installer->getSettings();
        $rootPath = realpath($_SERVER['DOCUMENT_ROOT']);
        $modules_path = $rootPath . DIRECTORY_SEPARATOR . $settings['modules_path'] . DIRECTORY_SEPARATOR;
        $publicPath = $rootPath . DIRECTORY_SEPARATOR . 'public';
        $assetsPath = $modules_path . $settings['module_name'] . DIRECTORY_SEPARATOR .'assets' . DIRECTORY_SEPARATOR;
        $testsPath = $modules_path . $settings['module_name'] . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR;
        $srcPath = $modules_path . $settings['module_name'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

        $jsPath = $publicPath . DIRECTORY_SEPARATOR . 'js';
        $cssPath = $publicPath . DIRECTORY_SEPARATOR . 'css';

        $controllersPath = $modules_path . $settings['module_name'] . DIRECTORY_SEPARATOR .'controllers';
        $viewsPath = $modules_path . $settings['module_name'] . DIRECTORY_SEPARATOR .'views';
        $modelPath = $modules_path . $settings['module_name'] . DIRECTORY_SEPARATOR
            . '..' . DIRECTORY_SEPARATOR
            . '..' . DIRECTORY_SEPARATOR
            . 'models' . DIRECTORY_SEPARATOR
            . ucfirst($settings['module_name']);
        $testModulePath = $rootPath . DIRECTORY_SEPARATOR
            . 'tests' . DIRECTORY_SEPARATOR
            . 'modules' . DIRECTORY_SEPARATOR
            . $settings['module_name'];
        $testModelPath = $rootPath . DIRECTORY_SEPARATOR
            . 'tests' . DIRECTORY_SEPARATOR
            . 'models' . DIRECTORY_SEPARATOR
            . $settings['module_name'];

        $finder = new Finder();
        $finder->directories()->in($modules_path . $settings['module_name']);
        $finder->path('src/')->ignoreUnreadableDirs();
        $finder->path('assets/')->ignoreUnreadableDirs();
        $finder->path('tests/')->ignoreUnreadableDirs();

        $this->removeDir($controllersPath);
        $this->removeDir($viewsPath);
        $this->removeDir($modelPath);
        $this->removeDir($jsPath . DIRECTORY_SEPARATOR . $settings['module_name']);
        $this->removeDir($cssPath . DIRECTORY_SEPARATOR . $settings['module_name']);
        $this->removeDir($testModulePath);
        $this->removeDir($testModelPath);


        foreach ($finder as $file) {
            if ($fs->exists($file->getRealPath())) {
                switch ($file->getBasename()) {
                    case 'controllers':
                        $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $controllersPath);
                        break;
                    case 'views':
                        $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $viewsPath);
                        break;
                    case 'models':
                        if (strpos($file->getRealPath(), 'tests'))
                            $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $testModelPath);
                        else $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $modelPath);
                        break;
                    case 'css':
                        $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $cssPath . DIRECTORY_SEPARATOR . $settings['module_name']);
                        break;
                    case 'js':
                        $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $jsPath . DIRECTORY_SEPARATOR . $settings['module_name']);
                        break;
                    case 'modules':
                        @$fs->mkdir($testModulePath, 0755);
                        $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $testModulePath . DIRECTORY_SEPARATOR . 'controllers/');
                        break;
                }
            }
        }
        // Remove folders
        if ($fs->exists($assetsPath)) {
            $fs->remove($assetsPath);
        }
        if ($fs->exists($srcPath)) {
            $fs->remove($srcPath);
        }
        if ($fs->exists($testsPath)) {
            $fs->remove($testsPath);
        }
    }

    public function removeDir($dir)
    {
        $fs = new Filesystem();
        if ($objs = glob($dir."/*")) {
            foreach($objs as $obj) {
                $fs->exists($obj) ? $this->removeDir($obj) : unlink($obj);
            }
        }
        $fs->remove($dir);
    }
}
