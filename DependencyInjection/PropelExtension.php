<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\DependencyInjection;

use Symfony\Bundle\WebProfilerBundle\DependencyInjection\WebProfilerExtension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * PropelExtension loads the PropelBundle configuration.
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
class PropelExtension extends Extension
{
    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (1 === count($config['database']['connections'])) {
            $defaultConnection = array_keys($config['database']['connections'])[0];
            if (!isset($config['runtime']['defaultConnection'])) {
                $config['runtime']['defaultConnection'] = $defaultConnection;
            }
            if (!isset($config['generator']['defaultConnection'])) {
                $config['generator']['defaultConnection'] = $defaultConnection;
            }
        }

        $container->setParameter('propel.logging', $config['runtime']['logging']);
        $container->setParameter('propel.configuration', $config);

        // Load services
        if (!$container->hasDefinition('propel')) {
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('propel.xml');
            $loader->load('converters.xml');
            $loader->load('security.xml');
            $loader->load('console.xml');

            $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('services.yml');

            if (($env = $container->getParameter('kernel.environment')) === 'dev' && class_exists(WebProfilerExtension::class)) {
                $container->setAlias(Profiler::class, 'profiler');
            }
        }
    }

    /**
     * @param array<mixed> $config
     * @param ContainerBuilder $container
     *
     * @return Configuration
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($container->getParameter('kernel.debug'), $container->getParameter('kernel.project_dir'));
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath(): string
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    public function getAlias(): string
    {
        return 'propel';
    }
}
