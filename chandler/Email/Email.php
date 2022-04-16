<?php declare(strict_types=1);
namespace Chandler\Email;
use Swift_SmtpTransport;
use Swift_Message;
use Swift_Mailer;
use Postmark\PostmarkClient;

class Email
{
    static function send(string $to, string $subject, string $html)
    {
        if(isset(CHANDLER_ROOT_CONF["email"]["postmark"])) {
            return (new PostmarkClient(CHANDLER_ROOT_CONF["email"]["postmark"]["key"]))->sendEmail(
                CHANDLER_ROOT_CONF["email"]["postmark"]["user"],
                $to,
                $subject,
                $html,
                strip_tags($html),
                NULL,
                true,
                NULL,
                NULL,
                NULL,
                ["Sensitivity" => "Company-Confidential"],
                NULL,
                "None",
                NULL,
                CHANDLER_ROOT_CONF["email"]["postmark"]["stream"]
            );
        } else {
            $transport = new Swift_SmtpTransport(CHANDLER_ROOT_CONF["email"]["host"], CHANDLER_ROOT_CONF["email"]["port"], CHANDLER_ROOT_CONF["email"]["ssl"] ? "ssl" : NULL);
            $transport->setUsername(CHANDLER_ROOT_CONF["email"]["user"] ?? CHANDLER_ROOT_CONF["email"]["addr"]);
            $transport->setPassword(CHANDLER_ROOT_CONF["email"]["pass"]);

            $message = new Swift_Message($subject);
            $message->getHeaders()->addTextHeader("Sensitivity", "Company-Confidential");
            $message->setFrom(CHANDLER_ROOT_CONF["email"]["addr"]);
            $message->setTo($to);
            $message->setBody($html, "text/html");

            $mailer = new Swift_Mailer($transport);
            return $mailer->send($message);
        }
    }
}
