<?php

/*
 * This file is part of the florentingarnier/spam-protection-bundle package.
 *
 * (c) Florentin Garnier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FlorentinGarnier\SpamProtectionBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private const DEFAULT_HOSTING_SOURCES = [
        'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/datacenter/ipv4.txt',
        'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/vpn/ipv4.txt',
    ];

    private const DEFAULT_TOR_SOURCES = [
        'https://check.torproject.org/torbulkexitlist',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('florentin_garnier_spam_protection');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('secret')
                    ->info('Signs the tokens and hashes the IP addresses written to the logs.')
                    ->defaultValue('%kernel.secret%')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('cache_pool')
                    ->info('PSR-6 pool keeping used tokens and attempt counters; it must be shared by every web server.')
                    ->defaultValue('cache.app')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('base_difficulty')
                    ->info('Leading zero bits required from the proof of work of a first submission.')
                    ->defaultValue(10)
                    ->min(1)
                    ->max(20)
                ->end()
                ->integerNode('maximum_attempts_per_hour')
                    ->info('Weighted attempts allowed per form and IP address before submissions are rejected.')
                    ->defaultValue(20)
                    ->min(1)
                ->end()
                ->arrayNode('ip_reputation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('list_path')
                            ->info('Compiled lists, written by spam-protection:refresh-ip-lists and read by every web server.')
                            ->defaultValue('%kernel.project_dir%/var/spam_protection/ip_reputation.php')
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('sources')
                            ->info('URLs of plain-text IPv4 / CIDR lists, per risk level.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('hosting')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(self::DEFAULT_HOSTING_SOURCES)
                                ->end()
                                ->arrayNode('tor')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(self::DEFAULT_TOR_SOURCES)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
