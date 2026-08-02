<?php

declare(strict_types=1);

use Chandler\Captcha\CaptchaManager;

function captcha_template(): string
{
    $conf = CHANDLER_ROOT_CONF["captcha"] ?? [];
    if (!($conf["enable"] ?? true)) {
        return "You have already verified that you are not a robot.";
    }

    $html = <<<'HTML'
            <div class="captcha">
                <img src="/commitcaptcha/captcha.webp" alt="Captcha" style="margin-bottom: 8px; width: 130px;" />
                <br/>
                <input type="text" name="captcha" placeholder="Enter 8 characters" />
            </div>
        HTML;

    return $html;
}

function check_captcha(?string $input = null): bool
{
    if (!$input) {
        $input = $_POST["captcha"] ?? 0;
    }

    return CaptchaManager::i()->verifyCaptcha((string) $input);
}
