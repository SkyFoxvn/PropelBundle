<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Propel\Bundle\PropelBundle\DependencyInjection\Security\UserProvider;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider\UserProviderFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ParentNodeDefinitionInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
/**
 * PropelFactory creates services for Propel user provider.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
class PropelFactory implements UserProviderFactoryInterface
{
    private string $key;
    private string $providerId;

    public function __construct(string $key, string $providerId)
    {
        $this->key = $key;
        $this->providerId = $providerId;
    }

    /**
     * @param ContainerBuilder $container
     * @param string $id
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, $id, $config): void
    {
        $container
            ->setDefinition($id, new ChildDefinition($this->providerId))
            ->addArgument($config['class'])
            ->addArgument($config['property'])
        ;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        /** @var ParentNodeDefinitionInterface $node */
        $node
            ->children()
            ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('property')->defaultNull()->end()
            ->end()
        ;
    }
}
