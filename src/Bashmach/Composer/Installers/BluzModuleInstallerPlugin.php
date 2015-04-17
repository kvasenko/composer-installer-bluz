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
    /**
     * @var BluzModuleInstaller
     */
    protected $installer;

    /**
     * @var null
     */
    protected $fs = null;

    /**
     * Path to public directory
     *
     * @var string
     */
    protected $publicPath;

    /**
     * Path to modules directory
     *
     * @var string
     */
    protected $modulePath;

    /**
     * Path to root directory
     *
     * @var string
     */
    protected $rootPath;

    /**
     * @var null|Finder
     */
    protected $finder = null;

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->installer = new BluzModuleInstaller($io, $composer);
        $composer->getInstallationmanager()->addInstaller($this->installer);
    }

    /**
     * Event registration
     *
     * @return array
     */
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

    /**
     * Event processing onPostInstallCmd
     *
     * @param CommandEvent $event
     */
    public function onPostInstallCmd(CommandEvent $event)
    {
        $this->moveFolders();
    }


    /**
     * Event processing onPostUpdateCmd
     *
     * @param CommandEvent $event
     */
    public function onPostUpdateCmd(CommandEvent $event)
    {
        $this->moveFolders();
    }


    /**
     *  Moving all directories
     */
    protected function moveFolders()
    {
        $this->setRootPath(realpath($_SERVER['DOCUMENT_ROOT']));
        $this->setPublicPath($this->getRootPath() . DIRECTORY_SEPARATOR . 'public');
        $this->setModulePath($this->getRootPath() . DIRECTORY_SEPARATOR . $this->installer->getSettings('modules_path') . DIRECTORY_SEPARATOR);

        $this->moveModule();
        $this->moveAssets();
        $this->moveTests();
        $this->removeEmptyDir();
    }

    /**
     * Remove directory
     *
     * @param $dir
     */
    protected function removeDir($dir)
    {
        if ($objs = glob($dir."/*")) {
            foreach($objs as $obj) {
                $this->getFs()->exists($obj) ? $this->removeDir($obj) : unlink($obj);
            }
        }
        $this->getFs()->remove($dir);
    }

    /**
     * Moving tests
     */
    protected function moveTests()
    {
        $finder = new Finder();
        $finder->directories()->in($this->getModulePath() . $this->installer->getSettings('module_name'))->path('tests/')->ignoreUnreadableDirs();
        $fs = $this->getFs();
        $testModulePath = $this->getRootPath() . DIRECTORY_SEPARATOR
            . 'tests' . DIRECTORY_SEPARATOR
            . 'modules' . DIRECTORY_SEPARATOR
            . $this->installer->getSettings('module_name');
        $testModelPath = $this->getRootPath() . DIRECTORY_SEPARATOR
            . 'tests' . DIRECTORY_SEPARATOR
            . 'models' . DIRECTORY_SEPARATOR
            . $this->installer->getSettings('module_name');

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

    /**
     * Moving assets files
     */
    protected function moveAssets()
    {
        $finder = new Finder();
        $finder->directories()->in($this->getModulePath() . $this->installer->getSettings('module_name'))->path('assets/')->ignoreUnreadableDirs();
        $fs = $this->getFs();

        foreach ($finder as $file) {
            $this->removeDir($this->getPublicPath() . DIRECTORY_SEPARATOR
                . $file->getBasename() . DIRECTORY_SEPARATOR
                . $this->installer->getSettings('module_name'));
            $fs->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $this->getPublicPath() . DIRECTORY_SEPARATOR
                . $file->getBasename() . DIRECTORY_SEPARATOR
                . $this->installer->getSettings('module_name'));
        }
    }

    /**
     * Moving controllers, models, views
     */
    protected function moveModule()
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

    /**
     * @return Filesystem
     */
    protected function getFs()
    {
        if (!$this->fs) {
            $this->fs = new Filesystem();
        }
        return $this->fs;
    }

    /**
     * @param $path
     */
    protected function setPublicPath($path)
    {
        $this->publicPath = $path;
    }

    /**
     * Return path public directory
     *
     * @return mixed
     */
    protected function getPublicPath()
    {
        return $this->publicPath;
    }

    /**
     * Set path root directory
     *
     * @param $path
     */
    protected function setRootPath($path)
    {
        $this->rootPath = $path;
    }

    /**
     * Return path root directory
     *
     * @return mixed
     */
    protected function getRootPath()
    {
        return $this->rootPath;
    }

    /**
     * Set path modules directory
     */
    protected function setModulePath()
    {
        $this->modulePath = $this->getRootPath() . DIRECTORY_SEPARATOR . $this->installer->getSettings('modules_path') . DIRECTORY_SEPARATOR;
    }

    /**
     * Return path modules directory
     *
     * @return mixed
     */
    protected function getModulePath()
    {
        return $this->modulePath;
    }

    /**
     * Removing empty folders
     */
    protected function removeEmptyDir()
    {
        $assetsPath = $this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR .'assets' . DIRECTORY_SEPARATOR;
        $testsPath = $this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR;
        $srcPath = $this->getModulePath() . $this->installer->getSettings('module_name')  . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

        $this->getFs()->remove($assetsPath);
        $this->getFs()->remove($testsPath);
        $this->getFs()->remove($srcPath);
    }
}
