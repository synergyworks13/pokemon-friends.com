<?php namespace obsession\Domain\Users\Leads\Notifications;

use obsession\Infrastructure\
{
    Interfaces\Domain\Users\Users\HandshakableInterface,
    Interfaces\Queues\ShouldQueueInterface,
    Contracts\Queues\QueueableTrait,
    Contracts\Notifications\Notification
};
use obsession\App\Notifications\
{
    Channels\AdministratorMailableChannel,
    Messages\CustomerMailMessage,
    Messages\MailableMessage
};

class HandshakeMailToConfirmReceptionToSender extends Notification
{

    /**
     * @var HandshakableInterface|null
     */
    protected $entity = null;

    /**
     * @var string
     */
    protected $subject = '';

    /**
     * @var string
     */
    protected $body = '';

    /**
     * HandshakeMailToConfirmedReceptionToSender constructor.
     *
     * @param HandshakableInterface $entity
     */
    public function __construct(HandshakableInterface $entity, $subject, $body)
    {
        $this->entity = $entity;
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed $notifiable
     *
     * @return array|string
     */
    public function via($notifiable)
    {
        return [
            'mail',
            AdministratorMailableChannel::class,
        ];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed $notifiable
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new CustomerMailMessage)
            ->subject(trans('leads.handshake_subject', [
                'subject' => $this->subject,
            ]))
            ->view(
                'emails.users.leads.handshake_mail_to_confirme_reception_to_sender',
                [
                    'civility_name' => $this->entity->civility_name,
                    'body' => nl2br($this->body),
                ]
            );
    }

    /**
     * @param $notifiable
     *
     * @return MailableMessage
     */
    public function toAdministrator($notifiable)
    {
        return (new MailableMessage())
            ->subject(trans('leads.handshake_subject', [
                'subject' => $this->subject,
            ]))
            ->view(
                'emails.users.leads.handshake_mail_to_administrator',
                [
                    'civility_name' => $this->entity->civility_name,
                    'body' => nl2br($this->body),
                ]
            );
    }
}