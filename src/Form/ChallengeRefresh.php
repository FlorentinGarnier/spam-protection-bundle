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

namespace FlorentinGarnier\SpamProtectionBundle\Form;

use Symfony\Component\Form\FormView;

/**
 * Tokens are single-use: a form submitted in JavaScript needs fresh ones after a failure.
 * The payload is the argument expected by refreshSpamProtection() in assets/spam-protection.js.
 */
final class ChallengeRefresh
{
    /**
     * @return array{renderedAt: string, challenge: string, difficulty: string}
     */
    public static function fromFormView(FormView $spamProtectionView): array
    {
        return [
            'renderedAt' => $spamProtectionView['rendered_at']->vars['value'],
            'challenge' => $spamProtectionView['proof_challenge']->vars['value'],
            'difficulty' => $spamProtectionView['proof_challenge']->vars['attr']['data-spam-protection-difficulty'],
        ];
    }
}
