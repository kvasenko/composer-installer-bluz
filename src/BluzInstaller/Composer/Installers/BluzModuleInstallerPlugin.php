<?php

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

    const PERMISSION_CODE = 0755;

    /**
     * @var null|Filesystem
     */
    protected $filesystem = null;

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
    public static function getSubscribedEvents(): array
    {
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
        $this->setModulePath(
            $this->getRootPath() . DIRECTORY_SEPARATOR .
            $this->installer->getSettings('modules_path') . DIRECTORY_SEPARATOR);

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
                $this->getFilesystem()->exists($obj) ? $this->removeDir($obj) : unlink($obj);
            }
        }
        $this->getFilesystem()->remove($dir);
    }

    /**
     * Moving tests
     */
    protected function moveTests()
    {
        $this->getFinder()
            ->directories()
            ->in(
                $this->getModulePath() . $this->installer->getSettings('module_name')
            )
            ->path('tests/')
            ->ignoreUnreadableDirs();

        $filesystem = $this->getFilesystem();

        $testModulePath = $this->getRootPath() . DIRECTORY_SEPARATOR .
            'tests' . DIRECTORY_SEPARATOR .
            'modules' . DIRECTORY_SEPARATOR .
            $this->installer->getSettings('module_name');

        $testModelPath = $this->getRootPath() . DIRECTORY_SEPARATOR .
            'tests' . DIRECTORY_SEPARATOR .
            'models' . DIRECTORY_SEPARATOR .
            $this->installer->getSettings('module_name');

        foreach ($this->getFinder() as $file) {

            if ($file->getBasename() === 'modules') {

                $this->removeDir($testModulePath);
                $filesystem->mkdir($testModulePath, self::PERMISSION_CODE);
                $filesystem->rename(
                    $file->getRealPath() . DIRECTORY_SEPARATOR,
                    $testModulePath . DIRECTORY_SEPARATOR . 'controllers/'
                );

            } else {

                $this->removeDir($testModelPath);
                $filesystem->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $testModelPath);

            }
        }
    }

    /**
     * Moving assets files
     */
    protected function moveAssets()
    {
        $this->getFinder()
            ->directories()->
            in(
                $this->getModulePath() .
                $this->installer->getSettings('module_name')
            )
            ->path('assets/')
            ->ignoreUnreadableDirs();

        $filesystem = $this->getFilesystem();

        foreach ($this->getFinder() as $file) {

            $this->removeDir($this->getPublicPath() . DIRECTORY_SEPARATOR .
                $file->getBasename() . DIRECTORY_SEPARATOR .
                $this->installer->getSettings('module_name'));

            $filesystem->rename(
                $file->getRealPath() . DIRECTORY_SEPARATOR,
                $this->getPublicPath() . DIRECTORY_SEPARATOR .
                $file->getBasename() . DIRECTORY_SEPARATOR .
                $this->installer->getSettings('module_name')
            );
        }
    }

    /**
     * Moving controllers, models, views
     */
    protected function moveModule()
    {
        $this->getFinder()
            ->directories()
            ->in(
                $this->getModulePath() .
                $this->installer->getSettings('module_name')
            )
            ->path('src/')
            ->ignoreUnreadableDirs();

        $filesystem = $this->getFilesystem();

        $modelPath = $this->getModulePath() . $this->installer->getSettings('module_name') . DIRECTORY_SEPARATOR .
            '..' . DIRECTORY_SEPARATOR .
            '..' . DIRECTORY_SEPARATOR .
            'models' . DIRECTORY_SEPARATOR .
            ucfirst($this->installer->getSettings('module_name'));

        foreach ($this->getFinder() as $file) {

            if ($file->getBasename() === 'models') {

                $this->removeDir($modelPath);
                $filesystem->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $modelPath);

            } else {

                $this->removeDir(
                    $this->getModulePath() .
                    $this->installer->getSettings('module_name') .
                    DIRECTORY_SEPARATOR .
                    $file->getBasename());

                $filesystem->rename(
                    $file->getRealPath() . DIRECTORY_SEPARATOR,
                    $this->getModulePath() .
                    $this->installer->getSettings('module_name') .
                    DIRECTORY_SEPARATOR .
                    $file->getBasename());
            }
        }
    }

    /**
     * @return Filesystem
     */
    protected function getFilesystem()
    {
        if (!$this->filesystem) {

            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    /**
     * @return Finder
     */
    protected function getFinder()
    {
        if (!$this->finder) {

            $this->finder = new Finder();
        }

        return $this->finder;
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
    protected function getPublicPath(): string
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
        $this->modulePath = $this->getRootPath() .
            DIRECTORY_SEPARATOR .
            $this->installer->getSettings('modules_path') .
            DIRECTORY_SEPARATOR;
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
        $assetsPath = $this->getModulePath() .
            $this->installer->getSettings('module_name') .
            DIRECTORY_SEPARATOR .'assets' .
            DIRECTORY_SEPARATOR;

        $testsPath = $this->getModulePath() .
            $this->installer->getSettings('module_name') .
            DIRECTORY_SEPARATOR . 'tests' .
            DIRECTORY_SEPARATOR;

        $srcPath = $this->getModulePath() .
            $this->installer->getSettings('module_name') .
            DIRECTORY_SEPARATOR .
            'src' .
            DIRECTORY_SEPARATOR;

        $this->getFilesystem()->remove($assetsPath);
        $this->getFilesystem()->remove($testsPath);
        $this->getFilesystem()->remove($srcPath);
    }
}
