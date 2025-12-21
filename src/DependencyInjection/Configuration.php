<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('zhortein_auditable');

        $root = $treeBuilder->getRootNode();
        $root
            ->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->arrayNode('async')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('transport')->defaultValue('async')->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
