<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;

use Propel\Generator\Command\ModelBuildCommand as BaseModelBuildCommand;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
class ModelBuildCommand extends WrappedCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('propel:model:build')
            ->setDescription('Build the model classes based on Propel XML schemas')

            ->addOption('connection', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Connection to use. Example: default, bookstore')
            ->addArgument('bundle', InputArgument::OPTIONAL, 'The bundle to generate model classes from')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function createSubCommandInstance()
    {
        return new BaseModelBuildCommand();
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubCommandArguments(InputInterface $input)
    {
        $arguments = [];
        $arguments['--output-dir'] = $this->getApplication()->getKernel()->getProjectDir().'/';

        $usesScript = $this->getContainer()->getParameter('propel.usesDatabaseLoaderScript');
        if($usesScript) {
            $arguments['--loader-script-dir'] = $this->getLoaderScriptDirectory();
        }

        return $arguments;
    }

    /**
     * {@inheritdoc}
     */
    protected function setupBuildTimeFiles()
    {
        parent::setupBuildTimeFiles();

        $loaderScriptDir = $this->getLoaderScriptDirectory();
        if($loaderScriptDir && !file_exists($loaderScriptDir)) {
            mkdir(dirname($loaderScriptDir), 0777, true);
            touch($loaderScriptDir);
        }
    }

    protected function getLoaderScriptDirectory(): ?string
    {
        $config = $this->getContainer()->getParameter('propel.configuration');
        return $config['paths']['loaderScriptDir'];
    }
}
