<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Service;

use App\AppBundle;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class SchemaLocator
{
    protected ContainerInterface $container;
    protected FileLocatorInterface $fileLocator;
    /** @var array{paths: array{schemaDir: string, sqlDir: string, migrationDir: string, composerDir: string, loaderScriptDir: string}} */
    protected array $configuration;

    /**
     * @param ContainerInterface $container
     * @param FileLocatorInterface $fileLocator
     * @param array{paths: array{schemaDir: string, sqlDir: string, migrationDir: string, composerDir: string, loaderScriptDir: string}} $configuration
     */
    public function __construct(ContainerInterface $container, FileLocatorInterface $fileLocator, array $configuration)
    {
        $this->container = $container;
        $this->fileLocator = $fileLocator;
        $this->configuration = $configuration;
    }

    /**
     * @param array<string, BundleInterface> $bundles
     *
     * @return array<string, array{?BundleInterface, \SplFileInfo}>
     */
    public function locateFromBundlesAndConfiguration(array $bundles): array
    {
        if (empty($bundles[AppBundle::NAME])) {
            $bundles[AppBundle::NAME] = new AppBundle($this->container);
        }

        $schemas = $this->locateFromBundles($bundles);

        $path = $this->configuration['paths']['schemaDir'].'/schema.xml';
        if (file_exists($path)) {
            $schema = new \SplFileInfo($path);
            $schemas[(string) $schema] = array(null, $schema);
        }

        return $schemas;
    }

    /**
     * @param array<string, BundleInterface> $bundles
     *
     * @return array<string, array{BundleInterface, \SplFileInfo}>
     */
    public function locateFromBundles(array $bundles): array
    {
        $schemas = array();
        foreach ($bundles as $bundle) {
            $schemas = array_merge($schemas, $this->locateFromBundle($bundle));
        }

        return $schemas;
    }

    /**
     * @param BundleInterface $bundle
     *
     * @return array<string, array{BundleInterface, \SplFileInfo}>
     */
    public function locateFromBundle(BundleInterface $bundle): array
    {
        // no bundle/bundle
        $dir = ($bundle->getName() === AppBundle::NAME)? $bundle->getPath().'/config' : $bundle->getPath().'/Resources/config';

        $finalSchemas = array();

        if (is_dir($dir)) {
            $finder  = new Finder();
            $schemas = $finder->files()->name('*schema.xml')->followLinks()->in($dir);

            if (iterator_count($schemas)) {
                foreach ($schemas as $schema) {
                    $logicalName = $this->transformToLogicalName($schema, $bundle);

                    $finalSchema = new \SplFileInfo($this->fileLocator->locate($logicalName));

                    $finalSchemas[(string) $finalSchema] = array($bundle, $finalSchema);
                }
            }
        }

        return $finalSchemas;
    }

    /**
     * @param  \SplFileInfo    $schema
     * @param  BundleInterface $bundle
     * @return string
     */
    protected function transformToLogicalName(\SplFileInfo $schema, BundleInterface $bundle)
    {
        // NOTE: for future research - i dont see why this function exists call of ->getRealPath() should do the job

        $schemaPath = str_replace(
            // no bundle/bundle
            $bundle->getPath(). DIRECTORY_SEPARATOR . ($bundle->getName() == AppBundle::NAME ? '' : 'Resources' . DIRECTORY_SEPARATOR) . 'config' . DIRECTORY_SEPARATOR,
            '',
            $schema->getRealPath()
        );

        //
        if ($bundle->getName() == AppBundle::NAME) {
            return sprintf('%s/config/%s', $bundle->getPath(), $schemaPath);
        }

        return sprintf('@%s/Resources/config/%s', $bundle->getName(), $schemaPath);
    }
}
