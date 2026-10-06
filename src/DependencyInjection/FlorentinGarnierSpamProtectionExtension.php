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

use FlorentinGarnier\SpamProtectionBundle\Lock\SymfonyTokenLock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class FlorentinGarnierSpamProtectionExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('florentin_garnier_spam_protection.secret', $config['secret']);
        $container->setParameter('florentin_garnier_spam_protection.base_difficulty', $config['base_difficulty']);
        $container->setParameter('florentin_garnier_spam_protection.maximum_attempts_per_hour', $config['maximum_attempts_per_hour']);
        $container->setParameter('florentin_garnier_spam_protection.ip_reputation.list_path', $config['ip_reputation']['list_path']);
        $container->setParameter('florentin_garnier_spam_protection.ip_reputation.sources', $config['ip_reputation']['sources']);
        $container->setAlias('florentin_garnier_spam_protection.cache', $config['cache_pool']);

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.php');

        if (null === $config['lock_factory']) {
            $container->removeDefinition(SymfonyTokenLock::class);
        } else {
            $container->setAlias('florentin_garnier_spam_protection.lock_factory', $config['lock_factory']);
        }
    }

    /**
     * Hides the protection fields wherever the form is rendered with form_row() or form_rest().
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', ['form_themes' => ['@FlorentinGarnierSpamProtection/form_theme.html.twig']]);
        }
    }
}
