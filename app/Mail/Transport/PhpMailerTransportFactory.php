<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class PhpMailerTransportFactory extends AbstractTransportFactory
{
    /**
     * {@inheritdoc}
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $isSecure = in_array($scheme, ['phpmailer+smtps', 'phpmailer+ssl']);
        
        return new PhpMailerTransport([
            'scheme' => $isSecure ? 'ssl' : 'tls',
            'host' => $dsn->getHost(),
            'port' => $dsn->getPort() ?: ($isSecure ? 465 : 587),
            'username' => $this->getUser($dsn),
            'password' => $this->getPassword($dsn),
            'timeout' => $dsn->getOption('timeout'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getSupportedSchemes(): array
    {
        return ['phpmailer+smtp', 'phpmailer+smtps', 'phpmailer+ssl', 'phpmailer+tls'];
    }
} 