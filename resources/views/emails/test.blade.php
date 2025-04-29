<x-mail::message>
# MailerSend Test Email

This is a test email sent using the MailerSend SMTP configuration. If you're seeing this email, your configuration is working correctly!

<x-mail::button :url="config('app.url')">
Visit Website
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
