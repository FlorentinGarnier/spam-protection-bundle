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

use FlorentinGarnier\SpamProtection\GibberishDetector;
use FlorentinGarnier\SpamProtection\IpReputation\IpReputation;
use FlorentinGarnier\SpamProtection\IpReputation\IpReputationList;
use FlorentinGarnier\SpamProtection\ProofOfWork;
use FlorentinGarnier\SpamProtection\SingleUseTokenRegistry;
use FlorentinGarnier\SpamProtection\SpamProtection;
use FlorentinGarnier\SpamProtection\SubmissionAttemptCounter;
use FlorentinGarnier\SpamProtection\SubmissionToken;
use FlorentinGarnier\SpamProtectionBundle\Command\RefreshIpReputationListsCommand;
use FlorentinGarnier\SpamProtectionBundle\Form\SpamProtectionType;
use FlorentinGarnier\SpamProtectionBundle\IpReputation\IpReputationListUpdater;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(SingleUseTokenRegistry::class)
        ->args([service('florentin_garnier_spam_protection.cache')]);

    $services->set(SubmissionToken::class)
        ->args([param('florentin_garnier_spam_protection.secret'), service(SingleUseTokenRegistry::class)]);

    $services->set(ProofOfWork::class)
        ->args([param('florentin_garnier_spam_protection.secret'), service(SingleUseTokenRegistry::class), param('florentin_garnier_spam_protection.base_difficulty')]);

    $services->set(SubmissionAttemptCounter::class)
        ->args([service('florentin_garnier_spam_protection.cache')]);

    $services->set(IpReputationList::class)
        ->args([param('florentin_garnier_spam_protection.ip_reputation.list_path')]);

    $services->set(IpReputation::class)
        ->args([service(IpReputationList::class)]);

    $services->set(GibberishDetector::class);

    $services->set(SpamProtection::class)
        ->args([
            service(SubmissionToken::class),
            service(ProofOfWork::class),
            service(SubmissionAttemptCounter::class),
            service(IpReputation::class),
            service(GibberishDetector::class),
            param('florentin_garnier_spam_protection.maximum_attempts_per_hour'),
        ]);

    $services->set(SpamProtectionType::class)
        ->args([
            service(SpamProtection::class),
            service('request_stack'),
            param('florentin_garnier_spam_protection.secret'),
            service('logger')->nullOnInvalid(),
        ])
        ->tag('form.type')
        ->tag('monolog.logger', ['channel' => 'spam_protection']);

    $services->set(IpReputationListUpdater::class)
        ->args([service('http_client'), service(IpReputationList::class), param('florentin_garnier_spam_protection.ip_reputation.sources')]);

    $services->set(RefreshIpReputationListsCommand::class)
        ->args([service(IpReputationListUpdater::class)])
        ->tag('console.command', ['command' => 'spam-protection:refresh-ip-lists']);
};
