<x-mail::message>
# PHPMailer Test

This is a test email sent using PHPMailer in Laravel.

<x-mail::button :url="url('/')">
Visit Website
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message> 