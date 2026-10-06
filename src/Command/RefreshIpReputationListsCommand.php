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

namespace FlorentinGarnier\SpamProtectionBundle\Command;

use FlorentinGarnier\SpamProtectionBundle\IpReputation\IpReputationListUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

#[AsCommand(
    name: 'spam-protection:refresh-ip-lists',
    description: 'Downloads the datacenter, VPN and Tor exit node IP lists used by the spam protection',
)]
final class RefreshIpReputationListsCommand extends Command
{
    public function __construct(
        private IpReputationListUpdater $updater,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $rangeCounts = $this->updater->update();
        } catch (\RuntimeException|HttpClientException $exception) {
            $io->error(sprintf('The IP lists were not updated, the previous ones are kept: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        foreach ($rangeCounts as $riskLevel => $rangeCount) {
            $io->writeln(sprintf('%s: %d range(s)', $riskLevel, $rangeCount));
        }

        $io->success('IP lists updated.');

        return Command::SUCCESS;
    }
}
