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

namespace FlorentinGarnier\SpamProtectionBundle\Tests\Command;

use FlorentinGarnier\SpamProtection\IpReputation\IpReputationList;
use FlorentinGarnier\SpamProtectionBundle\Command\RefreshIpReputationListsCommand;
use FlorentinGarnier\SpamProtectionBundle\IpReputation\IpReputationListUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RefreshIpReputationListsCommandTest extends TestCase
{
    private string $listPath;

    protected function setUp(): void
    {
        $this->listPath = sys_get_temp_dir() . '/ip_reputation_command_' . bin2hex(random_bytes(4)) . '.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->listPath);
    }

    public function testItReportsTheNumberOfRangesPerRiskLevel(): void
    {
        $tester = new CommandTester(new RefreshIpReputationListsCommand($this->createUpdater("198.51.100.0/24\n203.0.113.0/24")));
        $tester->execute([]);

        $this->assertSame(ConsoleCommand::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('hosting: 2 range(s)', $tester->getDisplay());
        $this->assertStringContainsString('tor: 2 range(s)', $tester->getDisplay());
    }

    public function testItFailsWithoutOverwritingTheListsWhenASourceIsUnusable(): void
    {
        $tester = new CommandTester(new RefreshIpReputationListsCommand($this->createUpdater('')));
        $tester->execute([]);

        $this->assertSame(ConsoleCommand::FAILURE, $tester->getStatusCode());
        $this->assertFileDoesNotExist($this->listPath);
    }

    private function createUpdater(string $body): IpReputationListUpdater
    {
        return new IpReputationListUpdater(new MockHttpClient(static fn (): MockResponse => new MockResponse($body)), new IpReputationList($this->listPath), [
            'hosting' => ['https://lists.example/hosting.txt'],
            'tor' => ['https://lists.example/tor.txt'],
        ]);
    }
}
