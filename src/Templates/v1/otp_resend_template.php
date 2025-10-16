<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OTP Verification</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, sans-serif;">

    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding:40px 0;">
                <!-- Card -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" 
                       style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    
                    <!-- Header with Gradient + Logo -->
                    <tr>
                        <td align="center" 
                            style="padding:25px;padding-bottom: 0px !important;">
                            <img src="https://thevsafe.com/static/image/logo/logo.png" alt="V SAFE Logo" style="height:55px;">
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px; color:#333333; font-size:16px; line-height:1.6;">
                            <h2 style="margin-top:0; color:#1d5d9a; font-size:22px; text-align:center;">OTP Verification</h2>
                            <p style="margin:0 0 15px 0; text-align:center;">Use the OTP below to verify your Account:</p>

                            <!-- OTP Box -->
                            <div style="background:#f0f8ff; border:2px solid #3196ca; border-radius:8px; padding:18px; 
                                        text-align:center; font-size:26px; font-weight:bold; color:#1d5d9a; letter-spacing:3px;">
                                <?= htmlspecialchars($otp) ?>
                            </div>

                            <p style="margin:20px 0 0 0; text-align:center; font-size:14px; color:#666;">
                                This OTP is valid for <strong>10 minutes</strong>.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f9f9f9; padding:20px; text-align:center; font-size:13px; color:#999;">
                            Regards,<br>
                            <strong>Thumbikkai Business Solutions Pvt Ltd</strong><br>
                            <span style="font-size:12px;">© <?= date('Y') ?> All rights reserved.</span>
                        </td>
                    </tr>
                </table>
                <!-- End Card -->
            </td>
        </tr>
    </table>

</body>
</html>
