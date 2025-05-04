<?php

namespace App\Mail\Transport;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Part\DataPart;
use Illuminate\Support\Facades\Log;

class PhpMailerTransport implements TransportInterface
{
    /**
     * The PHPMailer instance.
     *
     * @var \PHPMailer\PHPMailer\PHPMailer
     */
    protected $phpMailer;

    /**
     * Create a new PHPMailer transport instance.
     *
     * @param array $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->phpMailer = new PHPMailer(true);

        // Set debug level to 0 (no debug output)
        $this->phpMailer->SMTPDebug = 0; 

        // Configure SMTP
        if ($config['scheme'] === 'ssl') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['scheme'] === 'tls') {
            $this->phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $this->phpMailer->isSMTP();
        $this->phpMailer->Host = $config['host'];
        $this->phpMailer->Port = $config['port'];
        $this->phpMailer->SMTPAuth = !empty($config['username']) || !empty($config['password']);
        
        if ($this->phpMailer->SMTPAuth) {
            $this->phpMailer->Username = $config['username'];
            $this->phpMailer->Password = $config['password'];
        }

        if (!empty($config['timeout'])) {
            $this->phpMailer->Timeout = $config['timeout'];
        }

        $this->phpMailer->CharSet = PHPMailer::CHARSET_UTF8;
    }

    /**
     * {@inheritdoc}
     */
    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        if (!$message instanceof Email) {
            throw new \Exception('PHPMailer transport only supports Email messages');
        }

        try {
            $this->addFrom($message);
            $this->addRecipients($message);
            $this->addContent($message);
            $this->addAttachments($message);

            $this->phpMailer->send();

            return new SentMessage($message, $envelope ?? Envelope::create($message));
        } catch (Exception $e) {
            throw new \Exception("PHPMailer Error: {$e->getMessage()}");
        }
    }

    /**
     * Add the "from" address to the message.
     *
     * @param \Symfony\Component\Mime\Email $message
     * @return void
     */
    protected function addFrom(Email $message): void
    {
        $from = $message->getFrom();
        
        if (!empty($from)) {
            foreach ($from as $address) {
                $this->phpMailer->setFrom($address->getAddress(), $address->getName() ?: '');
            }
        }
    }

    /**
     * Add all of the recipients to the message.
     *
     * @param \Symfony\Component\Mime\Email $message
     * @return void
     */
    protected function addRecipients(Email $message): void
    {
        foreach ($message->getTo() as $address) {
            $this->phpMailer->addAddress($address->getAddress(), $address->getName() ?: '');
        }

        foreach ($message->getCc() as $address) {
            $this->phpMailer->addCC($address->getAddress(), $address->getName() ?: '');
        }

        foreach ($message->getBcc() as $address) {
            $this->phpMailer->addBCC($address->getAddress(), $address->getName() ?: '');
        }

        foreach ($message->getReplyTo() as $address) {
            $this->phpMailer->addReplyTo($address->getAddress(), $address->getName() ?: '');
        }
    }

    /**
     * Add the content to the message.
     *
     * @param \Symfony\Component\Mime\Email $message
     * @return void
     */
    protected function addContent(Email $message): void
    {
        $this->phpMailer->Subject = $message->getSubject();
        
        $htmlBody = $message->getHtmlBody();
        $textBody = $message->getTextBody();
        
        if (!empty($htmlBody)) {
            $this->phpMailer->isHTML(true);
            $this->phpMailer->Body = $htmlBody;
            
            if (!empty($textBody)) {
                $this->phpMailer->AltBody = $textBody;
            }
        } elseif (!empty($textBody)) {
            $this->phpMailer->isHTML(false);
            $this->phpMailer->Body = $textBody;
        }
    }

    /**
     * Add the attachments to the message.
     *
     * @param \Symfony\Component\Mime\Email $message
     * @return void
     */
    protected function addAttachments(Email $message): void
    {
        foreach ($message->getAttachments() as $attachment) {
            if ($attachment instanceof DataPart) {
                $this->phpMailer->addStringAttachment(
                    $attachment->getBody(),
                    $attachment->getFilename() ?: 'attachment',
                    PHPMailer::ENCODING_BASE64,
                    $attachment->getContentType()
                );
            }
        }
    }

    /**
     * Get the string representation of the transport.
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'phpmailer';
    }
} 