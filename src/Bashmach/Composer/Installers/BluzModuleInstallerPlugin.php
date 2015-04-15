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

        $controllersPath = $modules_path . $settings['module_name'] . DS .'controllers' . DS;
        $viewsPath = $modules_path . $settings['module_name'] . DS .'views' . DS;

        // Move folders
        if (is_dir($modules_path . $settings['module_name'] . DS . 'src' . DS . 'controllers' . DS)) {
            rename($modules_path . $settings['module_name'] . DS . 'src' . DS . 'controllers' . DS, $controllersPath);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'src' . DS . 'views' . DS)) {
            rename($modules_path . $settings['module_name'] . DS . 'src' . DS . 'views' . DS, $viewsPath);
        }

        $cssPath = $publicPath . DS . 'css';
        if (is_dir($modules_path . $settings['module_name'] . DS .'assets' . DS . 'css' . DS)) {
            rename($modules_path . $settings['module_name'] . DS .'assets' . DS . 'css', $cssPath . DS . $settings['module_name']);
        }

        $jsPath = $publicPath . DS . 'js';
        if (is_dir($modules_path . $settings['module_name'] . DS .'assets' . DS . 'js' . DS)) {
            rename($modules_path . $settings['module_name'] . DS .'assets' . DS . 'js' . DS, $jsPath . DS . $settings['module_name']);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'src' . DS . 'models' . DS)) {
            rename($modules_path . $settings['module_name'] . DS . 'src' . DS . 'models' . DS,
                $modules_path . $settings['module_name'] . DS . '..' . DS . '..' . DS . 'models' . DS . ucfirst($settings['module_name']) . DS);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'models')) {
            rename($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'models' . DS,
                $rootPath . DS . 'tests' . DS . 'models' . DS . ucfirst($settings['module_name']) . DS);
        }

        if (is_dir($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'modules')) {
            rename($modules_path . $settings['module_name'] . DS . 'tests' . DS . 'modules' . DS,
                $rootPath . DS . 'tests' . DS . 'modules' . DS . ucfirst($settings['module_name']) . DS);
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
}
