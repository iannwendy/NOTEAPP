<?php

namespace App\Console\Commands;

use App\Mail\PhpMailerTest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestMailerSend extends Command
{
    protected $signature = 'mail:test {email : The email address to send the test to}';
    protected $description = 'Send a test email using the configured mail settings with PHPMailer';

    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Sending test email to {$email} using PHPMailer...");
        
        // Enable debug log listener
        Log::listen(function ($log) {
            if (str_contains($log->message, 'PHPMailer')) {
                $this->line($log->message);
            }
        });
        
        try {
            Mail::to($email)->send(new PhpMailerTest());
            $this->info('Email sent successfully using PHPMailer!');
        } catch (\Exception $e) {
            $this->error('Failed to send email: ' . $e->getMessage());
            
            // Show the PHPMailer logs from the log file if any
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath)) {
                $logs = array_filter(
                    explode("\n", file_get_contents($logPath)),
                    fn($line) => str_contains($line, 'PHPMailer')
                );
                
                if (!empty($logs)) {
                    $this->line("\nPHPMailer Debug Logs:");
                    foreach (array_slice($logs, -20) as $log) {
                        $this->line($log);
                    }
                }
            }
        }
        
        return Command::SUCCESS;
    }
} 