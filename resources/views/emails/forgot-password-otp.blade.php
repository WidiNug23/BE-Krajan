<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password OTP</title>
    <style>
        /* RESET STYLES */
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #ECF3FF; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        
        /* FONT STACK MODERN */
        body, td, th {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        /* RESPONSIVE */
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: 0 !important; }
            .content-padding { padding: 24px !important; }
            .otp-text { font-size: 32px !important; letter-spacing: 8px !important; }
        }
    </style>
</head>
<body style="background-color: #ECF3FF; margin: 0; padding: 40px 0;">

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">
                
                <table class="email-container" role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 480px; background-color: #ffffff; border-radius: 24px; box-shadow: 0 8px 30px rgba(70, 95, 255, 0.08); overflow: hidden; margin: 0 auto;">
                    
                    <tr>
                        <td align="center" style="background-color: #465FFF; padding: 40px 0 30px 0;">
                            
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td align="center" valign="middle" style="background-color: rgba(255,255,255,0.2); width: 64px; height: 64px; border-radius: 50%;">
                                        <img src="https://img.icons8.com/ios-filled/100/ffffff/lock.png" alt="Reset Password" width="32" style="display: block; margin: 0 auto;">
                                    </td>
                                </tr>
                            </table>

                            <h2 style="color: #ffffff; margin: 16px 0 0 0; font-size: 20px; font-weight: 600; letter-spacing: 0.5px;">
                                Reset Password
                            </h2>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 40px 32px; text-align: center;">
                            
                            <p style="margin: 0 0 16px 0; font-size: 18px; color: #1f2937; font-weight: 700;">
                                Halo!
                            </p>
                            
                            <p style="margin: 0 0 32px 0; font-size: 15px; line-height: 1.6; color: #6b7280;">
                                Kami menerima permintaan untuk mengatur ulang password akun Anda di <strong>Layanan Desa Krajan</strong>. Gunakan kode di bawah ini untuk melanjutkan proses reset password.
                            </p>

                            <div style="background-color: #F5F8FF; border-radius: 16px; padding: 24px; margin-bottom: 24px; border: 1px dashed #465FFF;">
                                <span style="display: block; font-size: 12px; font-weight: 600; color: #465FFF; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px;">Kode OTP Reset Password</span>
                                <span class="otp-text" style="display: block; font-family: monospace; font-size: 42px; font-weight: 800; color: #1e1b4b; letter-spacing: 12px; line-height: 1;">
                                    {{ $otp }}
                                </span>
                            </div>

                            <div style="background-color: #fff1f2; border-radius: 8px; padding: 12px; display: inline-block;">
                                <p style="margin: 0; font-size: 13px; color: #be123c;">
                                    ⏰ Berlaku selama: <strong>10 Menit</strong>
                                </p>
                            </div>
                            
                            <p style="margin-top: 32px; font-size: 13px; color: #ef4444; font-weight: 600; line-height: 1.5;">
                                PENTING: Jangan berikan kode ini kepada siapa pun!
                            </p>

                            <p style="margin-top: 16px; font-size: 13px; color: #9ca3af; line-height: 1.5;">
                                *Jika Anda tidak pernah meminta reset password, mohon abaikan email ini dan pastikan akun Anda tetap aman.
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px; text-align: center; border-top: 1px solid #f3f4f6;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af; font-weight: 500;">
                                &copy; {{ date('Y') }} Layanan Desa Krajan
                            </p>
                            <p style="margin: 8px 0 0 0; font-size: 11px; color: #d1d5db;">
                                Krajan, Madiun, Jawa Timur
                            </p>
                        </td>
                    </tr>

                </table>
                
                <p style="margin-top: 24px; font-size: 12px; color: #94a3b8;">
                    Butuh bantuan? 
                    <a 
                        href="https://wa.me/6281234567890?text=Halo%20Admin%20Layanan%20Desa,%20saya%20mengalami%20kendala%20saat%20mereset%20password%20akun%20saya.%20Mohon%20bantuannya." 
                        target="_blank" 
                        style="color: #465FFF; text-decoration: none; font-weight: 600;"
                    >
                        Hubungi Admin
                    </a>
                </p>

            </td>
        </tr>
    </table>

</body>
</html>