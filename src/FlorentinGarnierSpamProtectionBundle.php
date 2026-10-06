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

namespace FlorentinGarnier\SpamProtectionBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class FlorentinGarnierSpamProtectionBundle extends Bundle
{
    /**
     * The bundle lives at the package root (config/, templates/, translations/), not in src/.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
