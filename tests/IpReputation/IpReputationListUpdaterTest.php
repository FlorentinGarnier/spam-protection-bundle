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

namespace FlorentinGarnier\SpamProtectionBundle\Tests\IpReputation;

use FlorentinGarnier\SpamProtection\IpReputation\IpReputation;
use FlorentinGarnier\SpamProtection\IpReputation\IpReputationList;
use FlorentinGarnier\SpamProtection\IpReputation\IpRiskLevel;
use FlorentinGarnier\SpamProtectionBundle\IpReputation\IpReputationListUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IpReputationListUpdaterTest extends TestCase
{
    private string $listPath;

    protected function setUp(): void
    {
        $this->listPath = sys_get_temp_dir() . '/ip_reputation_' . bin2hex(random_bytes(4)) . '/list.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->listPath);
        @rmdir(\dirname($this->listPath));
    }

    public function testItCompilesEveryRiskLevelFromItsSources(): void
    {
        $updater = $this->createUpdater([
            'https://lists.example/datacenter.txt' => "198.51.100.0/24\n",
            'https://lists.example/vpn.txt' => "203.0.113.0/24\n",
            'https://lists.example/tor.txt' => "192.0.2.1\n",
        ]);

        $counts = $updater->update();

        $reputation = new IpReputation(new IpReputationList($this->listPath));
        self::assertSame(['hosting' => 2, 'tor' => 1], $counts);
        self::assertSame(IpRiskLevel::Hosting, $reputation->getRiskLevel('198.51.100.10'));
        self::assertSame(IpRiskLevel::Hosting, $reputation->getRiskLevel('203.0.113.10'));
        self::assertSame(IpRiskLevel::Tor, $reputation->getRiskLevel('192.0.2.1'));
    }

    public function testItKeepsThePreviousListWhenASourceIsEmpty(): void
    {
        $this->createUpdater([
            'https://lists.example/datacenter.txt' => "198.51.100.0/24\n",
            'https://lists.example/vpn.txt' => "203.0.113.0/24\n",
            'https://lists.example/tor.txt' => "192.0.2.1\n",
        ])->update();

        $failingUpdater = $this->createUpdater([
            'https://lists.example/datacenter.txt' => "198.51.100.0/24\n",
            'https://lists.example/vpn.txt' => "203.0.113.0/24\n",
            'https://lists.example/tor.txt' => '<html>maintenance</html>',
        ]);

        try {
            $failingUpdater->update();
            self::fail('An empty source must abort the update.');
        } catch (\RuntimeException) {
        }

        self::assertSame(IpRiskLevel::Tor, (new IpReputation(new IpReputationList($this->listPath)))->getRiskLevel('192.0.2.1'));
    }

    /**
     * @param array<string, string> $bodies
     */
    private function createUpdater(array $bodies): IpReputationListUpdater
    {
        $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => new MockResponse($bodies[$url]));

        return new IpReputationListUpdater($httpClient, new IpReputationList($this->listPath), [
            'hosting' => ['https://lists.example/datacenter.txt', 'https://lists.example/vpn.txt'],
            'tor' => ['https://lists.example/tor.txt'],
        ]);
    }
}
