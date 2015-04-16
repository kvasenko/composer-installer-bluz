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
    protected $fs = null;
    protected $publicPath;
    protected $modulePath;
    protected $rootPath;
    protected $finder = null;
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
        $this->setRootPath(realpath($_SERVER['DOCUMENT_ROOT']));
        $this->setPublicPath($this->getRootPath() . DIRECTORY_SEPARATOR . 'public');
        $this->setModulePath($this->getRootPath() . DIRECTORY_SEPARATOR . $this->installer->getSettings('modules_path') . DIRECTORY_SEPARATOR);

        $this->moveModule();
        $this->moveAssets();
        $this->moveTests();
        $this->removeEmptyDir();
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

    public function moveTests()
    {
        $finder = new Finder();
        $settings = $this->installer->getSettings();
        $finder->directories()->in($this->getModulePath() . $settings['module_name'])->path('tests/')->ignoreUnreadableDirs();
        $fs = $this->getFs();
        $testModulePath = $this->getRootPath() . DIRECTORY_SEPARATOR
            . 'tests' . DIRECTORY_SEPARATOR
            . 'modules' . DIRECTORY_SEPARATOR
            . $settings['module_name'];
        $testModelPath = $this->getRootPath() . DIRECTORY_SEPARATOR
            . 'tests' . DIRECTORY_SEPARATOR
            . 'models' . DIRECTORY_SEPARATOR
            . $settings['module_name'];

        foreach ($finder as $file) {
            if ($file->getBasename() === 'modules') {
                $this->removeDir($testModulePath);
                $fs->mkdir($testModulePath, 0755);
                $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $testModulePath . DIRECTORY_SEPARATOR . 'controllers/');
            } else {
                $this->removeDir($testModelPath);
                $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $testModelPath);
            }
        }
    }

    public function moveAssets()
    {
        $finder = new Finder();
        $settings = $this->installer->getSettings();
        $finder->directories()->in($this->getModulePath() . $settings['module_name'])->path('assets/')->ignoreUnreadableDirs();
        $fs = $this->getFs();
        foreach ($finder as $file) {
            $this->removeDir($this->getPublicPath() . DIRECTORY_SEPARATOR
                . $file->getBasename() . DIRECTORY_SEPARATOR
                . $settings['module_name']);
            $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $this->getPublicPath() . DIRECTORY_SEPARATOR
                . $file->getBasename() . DIRECTORY_SEPARATOR
                . $settings['module_name']);
        }
    }

    public function moveModule()
    {
        $finder = new Finder();
        $finder->directories()->in($this->getModulePath() . $this->installer->getSettings('module_name'))->path('src/')->ignoreUnreadableDirs();

        $fs = $this->getFs();
        $modelPath = $this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR
            . '..' . DIRECTORY_SEPARATOR
            . '..' . DIRECTORY_SEPARATOR
            . 'models' . DIRECTORY_SEPARATOR
            . ucfirst($this->installer->getSettings('module_name'));

        foreach ($finder as $file) {
            if ($file->getBasename() === 'models') {
                $this->removeDir($modelPath);
                $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $modelPath);
            } else {
                $this->removeDir($this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR . $file->getBasename());
                $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR,
                    $this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR . $file->getBasename());
            }
        }
    }

    public function getFs()
    {
        if (!$this->fs) {
            $this->fs = new Filesystem();
        }
        return $this->fs;
    }

    public function setPublicPath($path)
    {
        $this->publicPath = $path;
    }

    public function getPublicPath()
    {
        return $this->publicPath;
    }

    public function setRootPath($path)
    {
        $this->rootPath = $path;
    }

    public function getRootPath()
    {
        return $this->rootPath;
    }

    public function setModulePath($path)
    {
        $this->modulePath = $this->getRootPath() . DIRECTORY_SEPARATOR . $this->installer->getSettings('modules_path') . DIRECTORY_SEPARATOR;
    }

    public function getModulePath()
    {
        return $this->modulePath;
    }

    public function getFinder()
    {
        if ($this->finder) {
            $this->finder = new Finder();
        }
        return $this->finder;
    }

    public function removeEmptyDir()
    {
        $assetsPath = $this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR .'assets' . DIRECTORY_SEPARATOR;
        $testsPath = $this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR;
        $srcPath = $this->getModulePath() . $this->installer->getSettings('module_name')  . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

        $this->getFs()->remove($assetsPath);
        $this->getFs()->remove($testsPath);
        $this->getFs()->remove($srcPath);
    }
}
