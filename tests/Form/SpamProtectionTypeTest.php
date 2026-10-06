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

use FlorentinGarnier\SpamProtection\IpReputation\IpRangeSet;
use FlorentinGarnier\SpamProtection\IpReputation\IpReputation;
use FlorentinGarnier\SpamProtection\IpReputation\IpReputationList;
use FlorentinGarnier\SpamProtection\SpamProtection;
use FlorentinGarnier\SpamProtection\Testing\SpamProtectionTestHelper;
use FlorentinGarnier\SpamProtectionBundle\Form\SpamProtectionType;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SpamProtectionTypeTest extends TypeTestCase
{
    private const SECRET = 'test-secret';

    private const SCOPE = 'contact';

    private const HOSTING_IP = '198.51.100.10';

    private RequestStack $requestStack;

    /** @var array<int, array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
    private array $logRecords = [];

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->requestStack->push(Request::create('/contact', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Firefox']));

        parent::setUp();
    }

    public function testItAcceptsAHumanSubmission(): void
    {
        $form = $this->submitForm();

        self::assertTrue($form->isValid());
        self::assertSame('spam_protection.accepted', $this->lastLogContext()['event']);
        self::assertSame('normal', $this->lastLogContext()['ip_risk']);
        self::assertArrayNotHasKey('reason', $this->lastLogContext());
    }

    public function testItRejectsASpamSubmissionWithAnErrorOnTheForm(): void
    {
        $form = $this->submitForm(['fax_number' => 'https://spam.example']);

        self::assertFalse($form->isValid());
        self::assertSame(SpamProtectionType::ERROR_MESSAGE, $form->getErrors()[0]->getMessage());
    }

    public function testItPutsTheErrorOnTheParentForm(): void
    {
        $form = $this->submitMessage('Bonjour', spamProtection: ['fax_number' => 'spam']);

        self::assertSame(SpamProtectionType::ERROR_MESSAGE, $form->getErrors()[0]->getMessage());
    }

    public function testItLogsTheRejectionWithoutPersonalData(): void
    {
        $this->submitForm(['fax_number' => 'https://spam.example']);

        $context = $this->lastLogContext();
        self::assertSame('spam_protection.rejected', $context['event']);
        self::assertSame('honeypot_filled', $context['reason']);
        self::assertSame(['contact', 'POST', '/contact'], [$context['form'], $context['method'], $context['path']]);
        self::assertSame(hash_hmac('sha256', '203.0.113.10', self::SECRET), $context['ip_hash']);
        self::assertSame(hash_hmac('sha256', 'Firefox', self::SECRET), $context['user_agent_hash']);
    }

    public function testItChecksTheContentFieldsOfTheParentForm(): void
    {
        $form = $this->submitMessage('dTqLzVbKxWmPfRjN');

        self::assertFalse($form->isValid());
        self::assertSame('unreadable_content', $this->lastLogContext()['reason']);
    }

    public function testItAcceptsAReadableMessage(): void
    {
        self::assertTrue($this->submitMessage('Hello, I would like a quote for a shelving unit.')->isValid());
    }

    public function testItChallengesTheVisitorAccordingToItsIpAddress(): void
    {
        $this->requestStack->push(Request::create('/', 'GET', server: ['REMOTE_ADDR' => self::HOSTING_IP]));

        self::assertSame('8', $this->renderView()['proof_challenge']->vars['attr']['data-spam-protection-difficulty']);
    }

    public function testItRendersFreshTokensAfterASubmission(): void
    {
        $form = $this->submitForm(['fax_number' => 'spam']);
        $submittedData = $form->getData();

        $view = $form->createView();

        self::assertNotSame($submittedData['rendered_at'], $view['rendered_at']->vars['value']);
        self::assertNotSame($submittedData['proof_challenge'], $view['proof_challenge']->vars['value']);
        self::assertSame('', $view['proof_solution']->vars['value']);
    }

    public function testItMarksTheFieldsForTheJavascriptSolver(): void
    {
        $view = $this->renderView();

        self::assertArrayHasKey('data-spam-protection-token', $view['rendered_at']->vars['attr']);
        self::assertArrayHasKey('data-spam-protection-challenge', $view['proof_challenge']->vars['attr']);
        self::assertArrayHasKey('data-spam-protection-solution', $view['proof_solution']->vars['attr']);
    }

    protected function getExtensions(): array
    {
        $logger = new class($this->logRecords) extends AbstractLogger {
            /** @var array<int, array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
            private array $records;

            /** @param array<int, array{level: mixed, message: string|\Stringable, context: array<mixed>}> $records */
            public function __construct(array &$records)
            {
                $this->records = &$records;
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };
        $ipReputationList = new IpReputationList(sys_get_temp_dir() . '/ip_reputation_type_' . bin2hex(random_bytes(4)) . '.php');
        $ipReputationList->save(['hosting' => IpRangeSet::fromCidrs(['198.51.100.0/24'])]);

        return [new PreloadedExtension([
            new SpamProtectionType(
                SpamProtection::create(self::SECRET, new ArrayAdapter(), new IpReputation($ipReputationList), baseDifficulty: 4),
                $this->requestStack,
                self::SECRET,
                $logger,
            ),
        ], [])];
    }

    /**
     * @param array<string, string> $values
     */
    private function submitForm(array $values = []): FormInterface
    {
        $form = $this->factory->create(SpamProtectionType::class, null, ['protection_scope' => self::SCOPE]);

        $form->submit(array_merge($this->solve($form->createView()), $values));

        return $form;
    }

    /**
     * @param array<string, string> $spamProtection
     */
    private function submitMessage(string $message, array $spamProtection = []): FormInterface
    {
        $form = $this->factory->createBuilder()
            ->add('message', TextareaType::class)
            ->add('spam_protection', SpamProtectionType::class, ['protection_scope' => self::SCOPE, 'content_fields' => ['message']])
            ->getForm()
        ;

        $form->submit([
            'message' => $message,
            'spam_protection' => array_merge($this->solve($form->createView()['spam_protection']), $spamProtection),
        ]);

        return $form;
    }

    /**
     * Fills the fields as a human would: honeypot left empty, form displayed for a few seconds, proof of work solved.
     *
     * @return array<string, string>
     */
    private function solve(FormView $view): array
    {
        $challenge = $view['proof_challenge']->vars;

        return [
            'fax_number' => '',
            'rendered_at' => SpamProtectionTestHelper::forgeSubmissionToken(self::SECRET, self::SCOPE, time() - 4),
            'proof_challenge' => $challenge['value'],
            'proof_solution' => SpamProtectionTestHelper::solveProofOfWork($challenge['value'], (int) $challenge['attr']['data-spam-protection-difficulty']),
        ];
    }

    private function renderView(): FormView
    {
        return $this->factory->create(SpamProtectionType::class, null, ['protection_scope' => self::SCOPE])->createView();
    }

    /**
     * @return array<mixed>
     */
    private function lastLogContext(): array
    {
        return $this->logRecords[array_key_last($this->logRecords)]['context'];
    }
}
