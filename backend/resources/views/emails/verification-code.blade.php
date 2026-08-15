<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: -apple-system, sans-serif; background:#0b0b10; color:#e8e8ef; padding:32px;">
    <table role="presentation" width="100%" style="max-width:420px; margin:0 auto;">
        <tr>
            <td style="padding-bottom:16px; font-size:15px; color:#a3a3b2;">
                Use this code to finish creating your account. It expires in {{ $ttlMinutes }} minutes.
            </td>
        </tr>
        <tr>
            <td style="padding:20px 0; text-align:center;">
                <span style="font-size:32px; font-weight:700; letter-spacing:8px; color:#ffffff;">{{ $code }}</span>
            </td>
        </tr>
        <tr>
            <td style="padding-top:16px; font-size:13px; color:#6f6f80;">
                Didn't request this? You can safely ignore this email — no account will be created without this code.
            </td>
        </tr>
    </table>
</body>
</html>
