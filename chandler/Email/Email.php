<?php declare(strict_types=1);
namespace Chandler\Email;
use Swift_SmtpTransport;
use Swift_Message;
use Swift_Mailer;

class Email
{
    static function send(string $to, string $subject, string $html)
    {
        $transport = new Swift_SmtpTransport(CHANDLER_ROOT_CONF["email"]["host"], CHANDLER_ROOT_CONF["email"]["port"], "ssl");
        $transport->setUsername(CHANDLER_ROOT_CONF["email"]["addr"]);
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