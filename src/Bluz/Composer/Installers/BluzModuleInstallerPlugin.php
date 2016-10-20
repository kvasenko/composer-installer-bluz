<?php

namespace Bluz\Composer\Installers;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\PackageEvent;
use Composer\Script\ScriptEvents;
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
     * @var null|Finder
     */
    protected $finder = null;

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->installer = new BluzModuleInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        $result = [
            ScriptEvents::POST_INSTALL_CMD => [
                ['onPostInstallCmd', 0]
            ],
            ScriptEvents::POST_UPDATE_CMD  => [
                ['onPostUpdateCmd', 0]
            ],
            ScriptEvents::PRE_PACKAGE_UNINSTALL  => [
                ['onPrePackageUninstall', 0]
            ]
        ];

        return $result;
    }

    public function onPostInstallCmd(Event $event)
    {
        $this->moveFolders();
    }

    public function onPrePackageUninstall(PackageEvent $event)
    {
        $this->removeModule();
        $this->removeModel();
        $this->removeTests();
        $this->removeAssetsFiles();
    }

    public function onPostUpdateCmd(Event $event)
    {
        $this->moveFolders();
    }

    protected function moveFolders()
    {
        $this->moveModule();
        $this->moveAssets();
        $this->moveTests();
        $this->removeEmptyDir();
    }

    protected function moveTests()
    {
        $this->getFinder()
            ->directories()
            ->in(
                $this->getModulesPath() . $this->installer->getSettings('module_name')
            )
            ->path('tests/')
            ->ignoreUnreadableDirs();

        $filesystem = $this->getFilesystem();

        foreach ($this->getFinder() as $file) {
            if ($file->getBasename() === 'modules') {
                $this->removeDir($this->getTestModulePath());
                $filesystem->mkdir($this->getTestModulePath(), self::PERMISSION_CODE);
                $filesystem->rename(
                    $file->getRealPath() . DIRECTORY_SEPARATOR,
                    $this->getTestModulePath() . DIRECTORY_SEPARATOR . 'controllers/'
                );
            } else {
                $this->removeDir($this->getTestModelPath());
                $filesystem->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $this->getTestModelPath());
            }
        }
    }

    protected function moveAssets()
    {
        $this->getFinder()
            ->directories()
            ->in(
                $this->getModulesPath() .
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

    protected function moveModule()
    {
        $this->getFinder()
            ->directories()
            ->in(
                $this->getModulesPath() .
                $this->installer->getSettings('module_name')
            )
            ->path('src/')
            ->ignoreUnreadableDirs();

        $filesystem = $this->getFilesystem();

        foreach ($this->getFinder() as $file) {
            if ($file->getBasename() === 'models') {
                $this->removeDir($this->getModelPath());
                $filesystem->rename($file->getRealPath() . DIRECTORY_SEPARATOR, $this->getModelPath());
            } else {
                $this->removeDir(
                    $this->getModulesPath() .
                    $this->installer->getSettings('module_name') .
                    DIRECTORY_SEPARATOR .
                    $file->getBasename());

                $filesystem->rename(
                    $file->getRealPath() . DIRECTORY_SEPARATOR,
                    $this->getModulesPath() .
                    $this->installer->getSettings('module_name') .
                    DIRECTORY_SEPARATOR .
                    $file->getBasename());
            }
        }
    }

    protected function getTestModulePath(): string
    {
        return $this->getRootPath() . DIRECTORY_SEPARATOR .
        'tests' . DIRECTORY_SEPARATOR .
        'modules' . DIRECTORY_SEPARATOR .
        $this->installer->getSettings('module_name');
    }

    protected function getTestModelPath(): string
    {
        return $this->getRootPath() . DIRECTORY_SEPARATOR .
        'tests' . DIRECTORY_SEPARATOR .
        'models' . DIRECTORY_SEPARATOR .
        $this->installer->getSettings('module_name');
    }

    protected function getModelPath(): string
    {
        return $this->getModulesPath() . DIRECTORY_SEPARATOR .
        '..' . DIRECTORY_SEPARATOR .
        'models' . DIRECTORY_SEPARATOR .
        ucfirst($this->installer->getSettings('module_name'));
    }

    protected function getFilesystem(): Filesystem
    {
        if (!$this->filesystem) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    protected function getFinder(): Finder
    {
        if (!$this->finder) {
            $this->finder = new Finder();
        }

        return $this->finder;
    }

    protected function getPublicPath(): string
    {
        return $this->getRootPath() . DIRECTORY_SEPARATOR . 'public';
    }

    protected function getRootPath(): string
    {
        return realpath($_SERVER['DOCUMENT_ROOT']);
    }

    protected function getModulesPath(): string
    {
        return $this->getRootPath() .
        DIRECTORY_SEPARATOR .
        $this->installer->getSettings('modules_path') .
        DIRECTORY_SEPARATOR;
    }

    protected function removeEmptyDir()
    {
        $assetsPath = $this->getModulesPath() .
            $this->installer->getSettings('module_name') .
            DIRECTORY_SEPARATOR .'assets' .
            DIRECTORY_SEPARATOR;

        $testsPath = $this->getModulesPath() .
            $this->installer->getSettings('module_name') .
            DIRECTORY_SEPARATOR . 'tests' .
            DIRECTORY_SEPARATOR;

        $srcPath = $this->getModulesPath() .
            $this->installer->getSettings('module_name') .
            DIRECTORY_SEPARATOR .
            'src' .
            DIRECTORY_SEPARATOR;

        $this->getFilesystem()->remove($assetsPath);
        $this->getFilesystem()->remove($testsPath);
        $this->getFilesystem()->remove($srcPath);
    }

    protected function removeDir(string $dir)
    {
        if ($objs = glob($dir."/*")) {
            foreach($objs as $obj) {
                $this->getFilesystem()->exists($obj) ? $this->removeDir($obj) : unlink($obj);
            }
        }
        $this->getFilesystem()->remove($dir);
    }

    protected function removeModel()
    {
        $this->getFilesystem()->remove($this->getModelPath());
    }

    protected function removeTests()
    {
        $this->getFilesystem()->remove($this->getTestModelPath());
        $this->getFilesystem()->remove($this->getTestModulePath());
    }

    protected function removeAssetsFiles()
    {
        $this->getFilesystem()->remove(
            $this->getPublicPath() . DIRECTORY_SEPARATOR .
            'js' . DIRECTORY_SEPARATOR .
            $this->installer->getSettings('module_name')
        );

        $this->getFilesystem()->remove(
            $this->getPublicPath() . DIRECTORY_SEPARATOR .
            'css' . DIRECTORY_SEPARATOR .
            $this->installer->getSettings('module_name')
        );

        $this->getFilesystem()->remove(
            $this->getPublicPath() . DIRECTORY_SEPARATOR .
            'fonts' . DIRECTORY_SEPARATOR .
            $this->installer->getSettings('module_name')
        );
    }

    protected function removeModule()
    {
        $this->getFinder()
            ->directories()
            ->in(
                $this->getModulesPath()
            )
            ->path($this->installer->getSettings('module_name'))
            ->ignoreUnreadableDirs();

        foreach ($this->getFinder() as $file) {
            $this->getFilesystem()->remove($file->getRealPath());
        }
    }
}
