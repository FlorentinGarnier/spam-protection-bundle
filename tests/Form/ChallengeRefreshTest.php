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

namespace FlorentinGarnier\SpamProtectionBundle\Tests\Form;

use FlorentinGarnier\SpamProtection\IpReputation\IpReputation;
use FlorentinGarnier\SpamProtection\IpReputation\IpReputationList;
use FlorentinGarnier\SpamProtection\SpamProtection;
use FlorentinGarnier\SpamProtectionBundle\Form\ChallengeRefresh;
use FlorentinGarnier\SpamProtectionBundle\Form\SpamProtectionType;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class ChallengeRefreshTest extends TypeTestCase
{
    public function testItExposesTheFreshTokensInTheFormatExpectedByRefreshSpamProtection(): void
    {
        $view = $this->factory->createBuilder()
            ->add('spam_protection', SpamProtectionType::class, ['protection_scope' => 'newsletter'])
            ->getForm()
            ->createView()
        ;

        $payload = ChallengeRefresh::fromFormView($view['spam_protection']);

        self::assertSame([
            'renderedAt' => $view['spam_protection']['rendered_at']->vars['value'],
            'challenge' => $view['spam_protection']['proof_challenge']->vars['value'],
            'difficulty' => '4',
        ], $payload);
    }

    protected function getExtensions(): array
    {
        $ipReputation = new IpReputation(new IpReputationList(sys_get_temp_dir() . '/missing_ip_reputation_list.php'));

        return [new PreloadedExtension([
            new SpamProtectionType(SpamProtection::create('secret', new ArrayAdapter(), $ipReputation, baseDifficulty: 4), new RequestStack(), 'secret'),
        ], [])];
    }
}
