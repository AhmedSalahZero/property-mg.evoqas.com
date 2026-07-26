<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Reset Password — VERO Property Management</title>
</head>
<body style="margin:0;padding:0;background-color:#0C1829;font-family:Figtree,Segoe UI,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0C1829;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:520px;background-color:#0E1E34;border:1px solid #1B3558;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:28px 28px 8px;">
                            <img
                                src="{{ $logoUrl }}"
                                alt="VERO Property Management"
                                width="180"
                                style="display:block;max-width:180px;height:auto;border:0;"
                            >
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 0;">
                            <div style="height:2px;width:120px;background:linear-gradient(90deg,#1490A8,transparent);border-radius:2px;margin:0 auto 20px;"></div>
                            <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;font-weight:800;color:#F1F5F9;text-align:center;">
                                Reset your password
                            </h1>
                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#6B96B8;text-align:center;">
                                @if (! empty($userName))
                                    Hi {{ $userName }}, we received a request to reset the password for your VERO Property Management account.
                                @else
                                    We received a request to reset the password for your VERO Property Management account.
                                @endif
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:8px 32px 24px;">
                            <a
                                href="{{ $url }}"
                                style="display:inline-block;background:linear-gradient(135deg,#095309,#063d06);border:1px solid rgba(83,214,105,0.4);border-radius:8px;padding:14px 28px;font-size:14px;font-weight:700;color:#FAEEDA;text-decoration:none;letter-spacing:0.03em;"
                            >
                                Reset Password
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px;">
                            <p style="margin:0 0 12px;font-size:12px;line-height:1.6;color:#6B96B8;text-align:center;">
                                This link expires in <strong style="color:#48C4D8;">{{ $expire }} minutes</strong>.
                                If you did not request a password reset, you can ignore this email.
                            </p>
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#1B3558;text-align:center;word-break:break-all;">
                                Or copy this link:<br>
                                <a href="{{ $url }}" style="color:#1490A8;text-decoration:none;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 24px;border-top:1px solid #1B3558;">
                            <p style="margin:0;font-size:11px;line-height:1.5;color:#1490A8;text-align:center;">
                                © {{ date('Y') }} VERO Property Management<br>
                                <span style="color:#6B96B8;">{{ $appName }} · Built by SQUAD Business Consulting</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
