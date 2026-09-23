<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 確認番号を知らせるメール。高齢の利用者が読みやすいよう、飾りのない文面で送る。
 */
class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $url,
        public readonly int $ttlMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【スマホの練習君】確認番号のお知らせ',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.verification-code',
        );
    }
}
