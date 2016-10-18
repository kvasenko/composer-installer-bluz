<?php

namespace Bashmach\Composer\Installers;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

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
        exec('echo 222 >> /tmp/hello.world');

        $extra     = $package->getExtra();
        $rootExtra = $this->composer->getPackage()->getExtra();
        $this->settings  = $extra['bluz'];

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
    public function supports($packageType): boolean
    {
        return $packageType === 'bluz-module';
    }

    protected function uninstallModule()
    {

    }

    /**
     * Return settings
     *
     * @param string $key
     * @return mixed
     */
    public function getSettings(string $key)
    {
        if (isset($this->settings[$key]))
            return $this->settings[$key];

        return $this->settings;
    }


}
