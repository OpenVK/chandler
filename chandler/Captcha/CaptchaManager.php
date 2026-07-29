<?php

declare(strict_types=1);

namespace Chandler\Captcha;

use Nette\Utils\Image;
use Chandler\Session\Session;
use Chandler\Patterns\TSimpleSingleton;

class CaptchaManager
{
    use TSimpleSingleton;

    private Session $session;

    private function __construct()
    {
        $this->session = Session::i();
    }

    private function generateColor(bool $maxOutRed = false): array
    {
        return [
            "red"   => $maxOutRed ? 255 : random_int(0, 150),
            "green" => random_int(0, 100),
            "blue"  => random_int(0, 150),
            "alpha" => 0,
        ];
    }

    private function generateCode(): string
    {
        return mb_strtoupper(str_replace("/", "+", base64_encode(openssl_random_pseudo_bytes(6))));
    }

    private function applyNoiseOnImage(Image $image, \Closure $fun): void
    {
        $_noise = (function () use ($image) {
            foreach (range(1, 33) as $x) {
                foreach (range(1, 13) as $y) {
                    $image->setPixel($x * 5, $y * 5, $this->generateColor());
                }
            }
        });

        $_noise();
        $fun();
        $_noise();
    }

    private function applyLinesOnImage(Image $image, \Closure $fun): void
    {
        $_lines = (function () use ($image) {
            foreach (range(1, random_int(2, 6)) as $i) {
                $image->line(random_int(0, 15), random_int(0, 65), random_int(150, 165), random_int(0, 65), $this->generateColor());
            }
        });

        $_lines();
        $fun();
        $_lines();
    }

    private function generateCaptchaImage(string $code): Image
    {
        $image = Image::fromBlank(165, 65, $this->generateColor());

        $this->applyNoiseOnImage($image, function () use ($image, $code) {
            $this->applyLinesOnImage($image, function () use ($image, $code) {
                $length = iconv_strlen($code);
                $offset = 165 / $length;

                for ($i = 0; $i < $length; $i++) {
                    $letter = $code[$i];
                    $font   = __DIR__ . "/data/Inter-Regular.ttf";
                    $x      = (int) ceil(0 + ($offset * $i));
                    $y      = random_int(45, 55);

                    $image->ttfText(random_int(18, 28), random_int(-10, 10), $x, $y, $this->generateColor(true), $font, $letter);
                }
            });
        });

        return $image;
    }

    public function getImage(): Image
    {
        $code  = $this->generateCode();
        $hash  = hash("crc32b", mb_strtolower($code));
        $image = $this->generateCaptchaImage($code);

        $nonce   = bin2hex(openssl_random_pseudo_bytes(SODIUM_CRYPTO_STREAM_NONCEBYTES / 2));
        $key     = substr(CHANDLER_ROOT_CONF["security"]["secret"], 0, SODIUM_CRYPTO_STREAM_KEYBYTES);
        $encHash = sodium_crypto_stream_xor($hash, $nonce, $key);
        $this->session->set("captcha", implode(":", [
            time(),
            $nonce,
            base64_encode($encHash),
        ]));

        return $image;
    }

    public function verifyCaptcha(?string $input = null): bool
    {
        $conf = CHANDLER_ROOT_CONF["captcha"] ?? [];
        if (!($conf["enable"] ?? true)) {
            return true;
        }

        if (!$input) {
            $input = $_POST["captcha"];
        }

        $data = $this->session->get("captcha");
        if (!$data) {
            return false;
        }

        $this->session->set("captcha", "");

        [$time, $nonce, $encHash] = explode(":", $data);
        if ((time() - $time) > 3600) {
            return false;
        }

        $key  = substr(CHANDLER_ROOT_CONF["security"]["secret"], 0, SODIUM_CRYPTO_STREAM_KEYBYTES);
        $hash = sodium_crypto_stream_xor(base64_decode($encHash), $nonce, $key);
        return hash_equals(hash("crc32b", mb_strtolower($input)), $hash);
    }
}
