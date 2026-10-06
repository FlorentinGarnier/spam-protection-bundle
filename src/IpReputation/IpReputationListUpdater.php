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

namespace FlorentinGarnier\SpamProtectionBundle\IpReputation;

use FlorentinGarnier\SpamProtection\IpReputation\IpRangeSet;
use FlorentinGarnier\SpamProtection\IpReputation\IpReputationList;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IpReputationListUpdater
{
    /**
     * @param array<string, list<string>> $sources URLs of plain-text CIDR lists, indexed by IpRiskLevel value
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private IpReputationList $list,
        private array $sources,
    ) {
    }

    /**
     * Downloads every source and atomically replaces the compiled list, which is left untouched if any source fails.
     *
     * @return array<string, int> number of ranges per risk level
     */
    public function update(): array
    {
        $rangeSets = array_map($this->download(...), $this->sources);

        $this->list->save($rangeSets);

        return array_map(static fn (IpRangeSet $rangeSet): int => \count($rangeSet), $rangeSets);
    }

    /**
     * @param list<string> $urls
     */
    private function download(array $urls): IpRangeSet
    {
        $cidrs = [];

        foreach ($urls as $url) {
            $lines = explode("\n", $this->httpClient->request('GET', $url)->getContent());

            if (0 === \count(IpRangeSet::fromCidrs($lines))) {
                throw new \RuntimeException(sprintf('The IP list "%s" contains no usable range.', $url));
            }

            $cidrs = [...$cidrs, ...$lines];
        }

        return IpRangeSet::fromCidrs($cidrs);
    }
}
