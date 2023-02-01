<?php

namespace Crm\RempMailerModule\Scenarios;

use Crm\ApplicationModule\ActiveRowFactory;
use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Crm\ScenariosModule\Events\NotificationTemplateParamsTrait;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;

class SendNotificationEmailToAddressesGenericEvent implements ScenarioGenericEventInterface
{
    use NotificationTemplateParamsTrait;

    private array $allowedMailTypeCodes = [];

    public function __construct(
        private UsersRepository $usersRepository,
        private Emitter $emitter,
        private MailTemplatesRepository $mailTemplatesRepository,
        private AddressesRepository $addressesRepository,
        private ActiveRowFactory $activeRowFactory,
        private SubscriptionsRepository $subscriptionsRepository,
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private PaymentsRepository $paymentsRepository,
        private RecurrentPaymentsResolver $recurrentPaymentsResolver,
    ) {
    }

    public function addAllowedMailTypeCodes(string ...$mailTypeCodes): void
    {
        foreach ($mailTypeCodes as $mailTypeCode) {
            $this->allowedMailTypeCodes[$mailTypeCode] = $mailTypeCode;
        }
    }

    public function getLabel(): string
    {
        return 'Send notification email to addresses';
    }

    public function getParams(): array
    {
        $mailTemplates = $this->mailTemplatesRepository->all($this->allowedMailTypeCodes);

        $mailTemplateOptions = [];
        foreach ($mailTemplates as $mailTemplate) {
            $mailTemplateOptions[$mailTemplate->code] = $mailTemplate->name;
        }

        return [
            new StringLabeledArrayParam('email_addresses', 'Email addresses', [], 'and', true),
            new StringLabeledArrayParam('email_codes', 'Email codes', $mailTemplateOptions, 'and'),
        ];
    }

    public function createEvents($options, $params): array
    {
        $templateParams = $this->getNotificationTemplateParams($params);

        $events = [];
        foreach ($options['email_addresses']->selection as $emailAddress) {
            $userRow = $this->activeRowFactory->create([
                'email' => $emailAddress,
            ]);

            foreach ($options['email_codes']->selection as $emailCode) {
                $events[] = new NotificationEvent(
                    $this->emitter,
                    $userRow,
                    $emailCode,
                    $templateParams
                );
            }
        }
        return $events;
    }
}
