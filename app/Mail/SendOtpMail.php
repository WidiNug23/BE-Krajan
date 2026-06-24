<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nama;
    public $otp;
    public $expired_at;

    public function __construct($nama, $otp, $expired_at)
    {
        $this->nama = $nama;
        $this->otp = $otp;
        $this->expired_at = $expired_at;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Verifikasi Akun Desa Krajan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.send-otp',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
