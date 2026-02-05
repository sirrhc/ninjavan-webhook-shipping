<?php
define('SMTP_HOST', '-'); // e.g., smtp.gmail.com
define('SMTP_PORT', 25); // 587 or 465 for SSL
define('SMTP_USER', '-');
define('SMTP_PASS', '-');
define('SMTP_FROM', '-');
define('SMTP_FROMNAME', '-');
// define('SMTP_SECURE', 'tls'); // tls or ssl

add_action('phpmailer_init', function ($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = SMTP_HOST;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = SMTP_PORT;
    $phpmailer->Username = SMTP_USER;
    $phpmailer->Password = SMTP_PASS;
    // $phpmailer->SMTPSecure = SMTP_SECURE;

    $phpmailer->setFrom(SMTP_FROM, SMTP_FROMNAME);
    $phpmailer->addReplyTo(SMTP_FROM, SMTP_FROMNAME); // Optional

    $phpmailer->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
});

?>