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

namespace FlorentinGarnier\SpamProtectionBundle\Tests\DependencyInjection;

use FlorentinGarnier\SpamProtection\IpReputation\IpReputation;
use FlorentinGarnier\SpamProtection\SpamProtection;
use FlorentinGarnier\SpamProtectionBundle\Command\RefreshIpReputationListsCommand;
use FlorentinGarnier\SpamProtectionBundle\DependencyInjection\FlorentinGarnierSpamProtectionExtension;
use FlorentinGarnier\SpamProtectionBundle\Form\SpamProtectionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\RequestStack;

final class FlorentinGarnierSpamProtectionExtensionTest extends TestCase
{
    public function testItWiresTheFormTypeAndTheCommandWithTheDefaultConfiguration(): void
    {
        $container = $this->loadExtension([]);
        $container->setAlias('test.form_type', SpamProtectionType::class)->setPublic(true);
        $container->setAlias('test.command', RefreshIpReputationListsCommand::class)->setPublic(true);

        $container->compile();

        self::assertInstanceOf(SpamProtectionType::class, $container->get('test.form_type'));
        self::assertInstanceOf(RefreshIpReputationListsCommand::class, $container->get('test.command'));
    }

    public function testItTagsTheServicesForTheFrameworkIntegrations(): void
    {
        $container = $this->loadExtension([]);

        self::assertTrue($container->getDefinition(SpamProtectionType::class)->hasTag('form.type'));
        self::assertSame('spam_protection', $container->getDefinition(SpamProtectionType::class)->getTag('monolog.logger')[0]['channel']);
        self::assertSame('spam-protection:refresh-ip-lists', $container->getDefinition(RefreshIpReputationListsCommand::class)->getTag('console.command')[0]['command']);
    }

    public function testItAppliesTheConfiguration(): void
    {
        $container = $this->loadExtension([
            'secret' => 'custom-secret',
            'cache_pool' => 'custom.cache',
            'base_difficulty' => 12,
            'maximum_attempts_per_hour' => 50,
            'ip_reputation' => ['list_path' => '/tmp/list.php', 'sources' => ['tor' => ['https://lists.example/tor.txt']]],
        ]);

        self::assertSame('custom-secret', $container->getParameter('florentin_garnier_spam_protection.secret'));
        self::assertSame('custom.cache', (string) $container->getAlias('florentin_garnier_spam_protection.cache'));
        self::assertSame(12, $container->getParameter('florentin_garnier_spam_protection.base_difficulty'));
        self::assertSame(50, $container->getParameter('florentin_garnier_spam_protection.maximum_attempts_per_hour'));
        self::assertSame('/tmp/list.php', $container->getParameter('florentin_garnier_spam_protection.ip_reputation.list_path'));
        self::assertSame(['https://lists.example/tor.txt'], $container->getParameter('florentin_garnier_spam_protection.ip_reputation.sources')['tor']);
    }

    public function testItDownloadsTheDatacenterVpnAndTorListsByDefault(): void
    {
        $sources = $this->loadExtension([])->getParameter('florentin_garnier_spam_protection.ip_reputation.sources');

        self::assertSame(['hosting', 'tor'], array_keys($sources));
        self::assertNotEmpty($sources['hosting']);
        self::assertNotEmpty($sources['tor']);
    }

    public function testItExposesTheLibraryServicesForAutowiring(): void
    {
        $container = $this->loadExtension([]);

        self::assertTrue($container->has(SpamProtection::class));
        self::assertTrue($container->has(IpReputation::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadExtension(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 'kernel-secret');
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->register('cache.app', ArrayAdapter::class);
        $container->register('custom.cache', ArrayAdapter::class);
        $container->register('request_stack', RequestStack::class);
        $container->register('http_client', MockHttpClient::class);

        (new FlorentinGarnierSpamProtectionExtension())->load([$config], $container);

        return $container;
    }
}
