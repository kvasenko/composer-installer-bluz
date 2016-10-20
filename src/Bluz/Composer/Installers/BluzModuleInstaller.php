<?php

namespace Bluz\Composer\Installers;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class BluzModuleInstaller extends LibraryInstaller
{

    /**
     * @var string
     */
    protected $settings;

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package): string
    {
        $extra     = $package->getExtra();
        $rootExtra = $this->composer->getPackage()->getExtra();
        $this->settings  = array_merge($rootExtra, $extra['bluz']);

        if (empty($this->settings['modules_path'])) {
            throw new \Exception('modules_path is not defined');
        }
        if (empty($this->settings['module_name'])) {
            throw new \Exception('module_name is not defined');
        }

        $path = $this->settings['modules_path'] . '/' . $this->settings['module_name'];

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return $packageType === 'bluz-module';
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->binaryInstaller->removeBinaries($package);
        $repo->removePackage($package);
    }

    /**
     * Return settings
     *
     * @param string $key
     * @return mixed
     */
    public function getSettings(string $key)
    {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }

        return $this->settings;
    }


}
