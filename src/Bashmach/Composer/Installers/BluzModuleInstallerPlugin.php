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
        $settings = $this->installer->getSettings();
        $rootPath = realpath($_SERVER['DOCUMENT_ROOT']);
        $modules_path = $rootPath . DS . $settings['modules_path'] . DS;
        $publicPath = $rootPath . DS . 'public';

        $controllersPath = $modules_path . $settings['module_name'] . DS .'controllers';
        $viewsPath = $modules_path . $settings['module_name'] . DS .'views';

        // Move folders
        if (is_dir($modules_path . $settings['module_name'] . DS . 'src' . DS . 'controllers' . DS)) {
            if (is_dir($controllersPath)) {
                $this->removeDir($controllersPath);
            }
            rename($modules_path . $settings['module_name'] . DS . 'src' . DS . 'controllers' . DS, $controllersPath);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'src' . DS . 'views' . DS)) {
            if (is_dir($viewsPath)) {
                $this->removeDir($viewsPath);
            }
            rename($modules_path . $settings['module_name'] . DS . 'src' . DS . 'views' . DS, $viewsPath);
        }

        $cssPath = $publicPath . DS . 'css';
        if (is_dir($modules_path . $settings['module_name'] . DS .'assets' . DS . 'css' . DS)) {
            if (is_dir($cssPath . DS . $settings['module_name'])) {
                $this->removeDir($cssPath . DS . $settings['module_name']);
            }
            rename($modules_path . $settings['module_name'] . DS .'assets' . DS . 'css', $cssPath . DS . $settings['module_name']);
        }

        $jsPath = $publicPath . DS . 'js';
        if (is_dir($modules_path . $settings['module_name'] . DS .'assets' . DS . 'js' . DS)) {
            if (is_dir($jsPath . DS . $settings['module_name'])) {
                $this->removeDir($jsPath . DS . $settings['module_name']);
            }
            rename($modules_path . $settings['module_name'] . DS .'assets' . DS . 'js' . DS, $jsPath . DS . $settings['module_name']);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'src' . DS . 'models' . DS)) {
            $modelPath = $modules_path . $settings['module_name'] . DS . '..' . DS . '..' . DS . 'models' . DS . ucfirst($settings['module_name']);
            if (is_dir($modelPath)) {
                $this->removeDir($modelPath);
            }
            rename($modules_path . $settings['module_name'] . DS . 'src' . DS . 'models' . DS,
                $modelPath);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'models')) {
            $testModelPath = $rootPath . DS . 'tests' . DS . 'models' . DS . $settings['module_name'];
            if (is_dir($testModelPath)) {
                @mkdir($testModelPath, 0755);
                $this->removeDir($testModelPath . DS . 'controllers');
            }
            rename($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'models' . DS,
                $testModelPath . DS);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'modules')) {
            $testModulePath = $rootPath . DS . 'tests' . DS . 'modules' . DS . $settings['module_name'] . DS . 'controllers';
            if (is_dir($testModulePath)) {
                $this->removeDir($testModulePath);
            }
            rename($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'modules' . DS,
                $testModulePath . DS);
        }

        // Remove folders
        if (is_dir($modules_path . $settings['module_name'] . DS .'assets' . DS)) {
            rmdir($modules_path . $settings['module_name'] . DS .'assets' . DS);
        }
        if (is_dir($modules_path . $settings['module_name'] . DS .'src' . DS)) {
            rmdir($modules_path . $settings['module_name'] . DS .'src' . DS);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'tests' . DS)) {
            rmdir($modules_path . $settings['module_name'] . DS . 'tests' . DS);
        }
    }

    public function removeDir($dir)
    {
        if ($objs = glob($dir."/*")) {
            foreach($objs as $obj) {
                is_dir($obj) ? $this->removeDir($obj) : unlink($obj);
            }
        }
        rmdir($dir);
    }
}
