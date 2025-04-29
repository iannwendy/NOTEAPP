<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailerSend extends Command
{
    protected $signature = 'mail:test {email : The email address to send the test to}';
    protected $description = 'Send a test email using the configured mail settings';

    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Sending test email to {$email}...");
        
        try {
            Mail::to($email)->send(new TestMail());
            $this->info('Email sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
        }
        
        return Command::SUCCESS;
    }
} 