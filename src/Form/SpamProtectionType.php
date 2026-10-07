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

use FlorentinGarnier\SpamProtection\SpamProtection;
use FlorentinGarnier\SpamProtection\Submission;
use FlorentinGarnier\SpamProtection\Verdict;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SpamProtectionType extends AbstractType
{
    public const ERROR_MESSAGE = 'florentin_garnier_spam_protection.invalid';

    private LoggerInterface $logger;

    public function __construct(
        private SpamProtection $spamProtection,
        private RequestStack $requestStack,
        private string $secret,
        ?LoggerInterface $logger = null,
        private ?TranslatorInterface $translator = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fax_number', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'tabindex' => '-1',
                ],
            ])
            ->add('rendered_at', HiddenType::class, [
                'attr' => [
                    'data-spam-protection-token' => 'true',
                ],
            ])
            ->add('proof_challenge', HiddenType::class, [
                'attr' => [
                    'data-spam-protection-challenge' => 'true',
                ],
            ])
            ->add('proof_solution', HiddenType::class, [
                'attr' => [
                    'data-spam-protection-solution' => 'true',
                ],
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options): void {
                $this->verifySubmission($event->getForm(), $options['protection_scope'], $options['content_fields']);
            })
        ;
    }

    /**
     * Tokens are single-use, so every rendering (including a re-rendering after a failed submission) issues fresh ones.
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $challenge = $this->spamProtection->issueChallenge($options['protection_scope'], $this->getIpAddress());

        $view['rendered_at']->vars['value'] = $challenge->submissionToken;
        $view['proof_challenge']->vars['value'] = $challenge->proofOfWorkChallenge;
        $view['proof_challenge']->vars['attr']['data-spam-protection-difficulty'] = (string) $challenge->difficulty;
        $view['proof_solution']->vars['value'] = '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'protection_scope' => 'unknown',
            'content_fields' => [],
        ]);
        $resolver->setAllowedTypes('protection_scope', 'string');
        $resolver->setInfo('protection_scope', 'Identifies the form, so that tokens and rate limits are not shared between forms.');
        $resolver->setAllowedTypes('content_fields', 'string[]');
        $resolver->setInfo('content_fields', 'Sibling fields whose free text is rejected when made of random characters.');
    }

    /**
     * @param list<string> $contentFields
     */
    private function verifySubmission(FormInterface $form, string $scope, array $contentFields): void
    {
        $verdict = $this->spamProtection->verify(new Submission(
            scope: $scope,
            honeypot: (string) $form->get('fax_number')->getData(),
            submissionToken: (string) $form->get('rendered_at')->getData(),
            proofOfWorkChallenge: (string) $form->get('proof_challenge')->getData(),
            proofOfWorkSolution: (string) $form->get('proof_solution')->getData(),
            contents: $this->collectContents($form->getParent(), $contentFields),
        ), $this->getIpAddress());

        if ($verdict->isAccepted()) {
            $this->logger->info('Spam-protected form submission accepted.', $this->createLogContext($scope, $verdict));

            return;
        }

        $this->logger->warning('Spam-protected form submission rejected.', $this->createLogContext($scope, $verdict));
        ($form->getParent() ?? $form)->addError(new FormError($this->translateErrorMessage(), self::ERROR_MESSAGE));
    }

    /**
     * Form themes render the message of a FormError as it is; the untranslated key remains its message template.
     */
    private function translateErrorMessage(): string
    {
        return $this->translator?->trans(self::ERROR_MESSAGE, [], 'messages') ?? self::ERROR_MESSAGE;
    }

    /**
     * @param list<string> $contentFields
     *
     * @return list<string>
     */
    private function collectContents(?FormInterface $parent, array $contentFields): array
    {
        $contents = [];

        foreach ($contentFields as $field) {
            if (null !== $parent && $parent->has($field)) {
                $contents[] = (string) $parent->get($field)->getData();
            }
        }

        return $contents;
    }

    private function getIpAddress(): string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
    }

    /**
     * @return array<string, string>
     */
    private function createLogContext(string $scope, Verdict $verdict): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $context = [
            'event' => 'spam_protection.' . ($verdict->isAccepted() ? 'accepted' : 'rejected'),
            'form' => $scope,
            'method' => $request?->getMethod() ?? 'unknown',
            'path' => $request?->getPathInfo() ?? 'unknown',
            'ip_hash' => hash_hmac('sha256', $request?->getClientIp() ?? 'unknown', $this->secret),
            'user_agent_hash' => hash_hmac('sha256', $request?->headers->get('User-Agent', 'unknown') ?? 'unknown', $this->secret),
            'ip_risk' => $verdict->ipRiskLevel->value,
        ];

        if (null !== $verdict->rejectionReason) {
            $context['reason'] = $verdict->rejectionReason->value;
        }

        return $context;
    }
}
